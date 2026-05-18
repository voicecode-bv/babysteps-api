<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use OpenApi\Attributes as OA;

/**
 * Chunked upload sessions voor grote media (vooral videos). Het BFF leest het
 * lokale file:// pad in chunks van schijf en streamt die naar deze endpoints,
 * zodat een 200 MB video niet als één multipart-blob hoeft te passen door
 * een HTTP-timeout of het PHP-geheugen.
 *
 * Lifecycle:
 *   POST   /api/uploads                           → {upload_id, chunk_size, max_chunks, max_total_bytes}
 *   POST   /api/uploads/{upload}/chunk            → write chunk; final=true assembleert + retourneert upload_token
 *   DELETE /api/uploads/{upload}                  → abort + cleanup
 *
 * Het `upload_token` (gelijk aan upload_id) is daarna één keer inwisselbaar
 * via `/api/posts` als `media_token` of `media_tokens[]` veld, zolang de
 * sessie nog niet door de GC is opgeruimd (zie posts:gc-upload-sessions in
 * console.php). De token is per definitie user-gebonden.
 */
class UploadController extends Controller
{
    public const CHUNK_SIZE_BYTES = 4 * 1024 * 1024; // 4 MB ruwe bytes (multipart, geen base64-overhead)

    public const MAX_CHUNKS = 256;

    public const MAX_TOTAL_BYTES = 500 * 1024 * 1024; // 500 MB hard cap; client- en serverside checks pakken kleinere quota daarbinnen.

    private const MAX_CHUNK_BYTES = 8 * 1024 * 1024; // ruimte voor laatste chunk + minimale multipart-overhead.

    #[OA\Post(
        path: '/api/uploads',
        summary: 'Initialise a chunked upload session',
        description: 'Returns an upload_id plus the chunk-size constraints. Each subsequent chunk POST references this upload_id. Sessions die langer dan 24 uur openstaan worden ge-GC\'d.',
        tags: ['Uploads'],
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(
                response: 201,
                description: 'Session initialised',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'upload_id', type: 'string', format: 'uuid'),
                    new OA\Property(property: 'chunk_size', type: 'integer', description: 'Aanbevolen chunk size in bytes.'),
                    new OA\Property(property: 'max_chunks', type: 'integer'),
                    new OA\Property(property: 'max_total_bytes', type: 'integer'),
                ]),
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ],
    )]
    public function init(Request $request): JsonResponse
    {
        $uploadId = (string) Str::uuid();
        $directory = self::sessionDirectory($uploadId);

        File::ensureDirectoryExists($directory);

        file_put_contents($directory.'/meta.json', json_encode([
            'user_id' => $request->user()->id,
            'created_at' => now()->toIso8601String(),
        ]));

        return response()->json([
            'upload_id' => $uploadId,
            'chunk_size' => self::CHUNK_SIZE_BYTES,
            'max_chunks' => self::MAX_CHUNKS,
            'max_total_bytes' => self::MAX_TOTAL_BYTES,
        ], 201);
    }

    #[OA\Post(
        path: '/api/uploads/{upload}/chunk',
        summary: 'Upload a chunk',
        description: 'Append a chunk to an active upload session. Multipart bytes (`chunk` veld) of base64 (`data` veld) zijn allebei toegestaan; multipart heeft de voorkeur omdat het ~33% kleiner is over the wire.',
        tags: ['Uploads'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'upload', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['sequence'],
                    properties: [
                        new OA\Property(property: 'sequence', type: 'integer'),
                        new OA\Property(property: 'chunk', type: 'string', format: 'binary', description: 'Ruwe chunk bytes. Of: stuur base64 via `data`.'),
                        new OA\Property(property: 'data', type: 'string', description: 'Base64 chunk bytes. Of: stuur multipart via `chunk`.', nullable: true),
                        new OA\Property(property: 'final', type: 'boolean'),
                    ],
                ),
            ),
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Chunk stored (intermediate) of upload finalised',
                content: new OA\JsonContent(oneOf: [
                    new OA\Schema(properties: [
                        new OA\Property(property: 'received', type: 'boolean'),
                        new OA\Property(property: 'sequence', type: 'integer'),
                    ]),
                    new OA\Schema(properties: [
                        new OA\Property(property: 'upload_token', type: 'string', format: 'uuid'),
                        new OA\Property(property: 'mime_type', type: 'string'),
                        new OA\Property(property: 'size_bytes', type: 'integer'),
                    ]),
                ]),
            ),
            new OA\Response(response: 404, description: 'Session not found'),
            new OA\Response(response: 422, description: 'Validation error'),
        ],
    )]
    public function chunk(Request $request, string $uploadId): JsonResponse
    {
        if (! Str::isUuid($uploadId)) {
            return response()->json(['message' => __('Invalid upload session.')], 404);
        }

        $directory = self::sessionDirectory($uploadId);

        if (! is_dir($directory) || ! $this->sessionBelongsToUser($directory, $request)) {
            return response()->json(['message' => __('Invalid upload session.')], 404);
        }

        $validated = $request->validate([
            'sequence' => ['required', 'integer', 'min:0', 'max:'.(self::MAX_CHUNKS - 1)],
            'chunk' => ['required_without:data', 'file', 'max:'.(self::MAX_CHUNK_BYTES / 1024)],
            'data' => ['required_without:chunk', 'string'],
            'final' => ['sometimes', 'boolean'],
            'mime_type' => ['sometimes', 'string', 'max:127'],
        ]);

        $binary = $this->resolveChunkBytes($request, $validated);

        if ($binary === null) {
            return response()->json(['message' => __('Invalid chunk data.')], 422);
        }

        if (strlen($binary) > self::MAX_CHUNK_BYTES) {
            return response()->json(['message' => __('Chunk too large.')], 422);
        }

        $chunkPath = $directory.'/'.self::chunkFilename($validated['sequence']);
        file_put_contents($chunkPath, $binary);

        if ($this->totalSize($directory) > self::MAX_TOTAL_BYTES) {
            $this->cleanup($directory);

            return response()->json(['message' => __('Upload exceeds maximum size.')], 422);
        }

        if (! ($validated['final'] ?? false)) {
            return response()->json(['received' => true, 'sequence' => $validated['sequence']]);
        }

        return $this->finalize($directory, $uploadId, $validated['mime_type'] ?? null);
    }

    #[OA\Delete(
        path: '/api/uploads/{upload}',
        summary: 'Abort an upload session',
        description: 'Discard any uploaded chunks. Idempotent — onbekende of al opgeruimde sessies geven 200.',
        tags: ['Uploads'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'upload', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Aborted'),
        ],
    )]
    public function abort(Request $request, string $uploadId): JsonResponse
    {
        if (! Str::isUuid($uploadId)) {
            return response()->json(['ok' => true]);
        }

        $directory = self::sessionDirectory($uploadId);

        if (is_dir($directory) && $this->sessionBelongsToUser($directory, $request)) {
            $this->cleanup($directory);
        }

        return response()->json(['ok' => true]);
    }

    public static function sessionsDirectory(): string
    {
        return storage_path('app/private/uploads');
    }

    public static function sessionDirectory(string $uploadId): string
    {
        return self::sessionsDirectory().'/'.$uploadId;
    }

    /**
     * Resolve een already-finalised session voor consumptie door PostController.
     * Geeft `null` als de sessie niet bestaat, niet bij de user hoort, of nog
     * niet geassembleerd is.
     *
     * @return ?array{path: string, mime_type: string, size: int}
     */
    public static function consumeAssembled(string $uploadId, string $userId): ?array
    {
        if (! Str::isUuid($uploadId)) {
            return null;
        }

        $directory = self::sessionDirectory($uploadId);
        $metaPath = $directory.'/meta.json';
        $assembledPath = $directory.'/assembled.bin';

        if (! is_dir($directory) || ! file_exists($metaPath) || ! file_exists($assembledPath)) {
            return null;
        }

        $meta = json_decode((string) file_get_contents($metaPath), true) ?? [];

        if (($meta['user_id'] ?? null) !== $userId || empty($meta['finalized'])) {
            return null;
        }

        return [
            'path' => $assembledPath,
            'mime_type' => $meta['mime_type'] ?? 'application/octet-stream',
            'size' => (int) ($meta['size'] ?? filesize($assembledPath)),
        ];
    }

    public static function destroySession(string $uploadId): void
    {
        $directory = self::sessionDirectory($uploadId);

        if (is_dir($directory)) {
            File::deleteDirectory($directory);
        }
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function resolveChunkBytes(Request $request, array $validated): ?string
    {
        if ($request->hasFile('chunk')) {
            $file = $request->file('chunk');

            return $file !== null ? (string) file_get_contents($file->getPathname()) : null;
        }

        if (isset($validated['data'])) {
            $decoded = base64_decode((string) $validated['data'], true);

            return $decoded === false ? null : $decoded;
        }

        return null;
    }

    private function sessionBelongsToUser(string $directory, Request $request): bool
    {
        $metaPath = $directory.'/meta.json';

        if (! file_exists($metaPath)) {
            return false;
        }

        $meta = json_decode((string) file_get_contents($metaPath), true) ?? [];

        return ($meta['user_id'] ?? null) === $request->user()->id;
    }

    private function totalSize(string $directory): int
    {
        $total = 0;

        foreach (glob($directory.'/chunk_*') ?: [] as $file) {
            $total += filesize($file) ?: 0;
        }

        return $total;
    }

    private static function chunkFilename(int $sequence): string
    {
        return 'chunk_'.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }

    private function finalize(string $directory, string $uploadId, ?string $clientMimeType): JsonResponse
    {
        $chunks = glob($directory.'/chunk_*') ?: [];

        if ($chunks === []) {
            $this->cleanup($directory);

            return response()->json(['message' => __('Upload has no chunks.')], 422);
        }

        sort($chunks, SORT_STRING);

        $assembledPath = $directory.'/assembled.bin';
        $output = fopen($assembledPath, 'wb');

        if ($output === false) {
            $this->cleanup($directory);

            return response()->json(['message' => __('Could not assemble upload.')], 500);
        }

        foreach ($chunks as $chunk) {
            $handle = fopen($chunk, 'rb');

            if ($handle === false) {
                fclose($output);
                @unlink($assembledPath);
                $this->cleanup($directory);

                return response()->json(['message' => __('Could not assemble upload.')], 500);
            }

            stream_copy_to_stream($handle, $output);
            fclose($handle);
            @unlink($chunk);
        }

        fclose($output);

        $size = filesize($assembledPath) ?: 0;

        if ($size === 0 || $size > self::MAX_TOTAL_BYTES) {
            $this->cleanup($directory);

            return response()->json(['message' => __('Invalid assembled upload.')], 422);
        }

        $detectedMime = File::mimeType($assembledPath) ?: ($clientMimeType ?: 'application/octet-stream');

        $metaPath = $directory.'/meta.json';
        $meta = json_decode((string) file_get_contents($metaPath), true) ?? [];
        $meta['finalized'] = true;
        $meta['mime_type'] = $detectedMime;
        $meta['size'] = $size;
        file_put_contents($metaPath, json_encode($meta));

        return response()->json([
            'upload_token' => $uploadId,
            'mime_type' => $detectedMime,
            'size_bytes' => $size,
        ]);
    }

    private function cleanup(string $directory): void
    {
        if (is_dir($directory)) {
            File::deleteDirectory($directory);
        }
    }
}
