<?php

namespace App\Jobs;

use App\Enums\MediaStatus;
use App\Models\PostMedia;
use App\Support\MediaUrl;
use App\Support\UserStorage;
use Codingmonkeys\FileFlux\FileFlux;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Stuurt een video naar FileFlux voor HLS-transcoding. De originele upload
 * wordt eerst gearchiveerd naar `users/{uid}/originals/posts/` zodat FileFlux
 * een stabiele bron heeft die niet door tussentijdse opruimacties verdwijnt.
 *
 * Faalt FileFlux na alle retries, dan dispatchen we de oude `TranscodeVideo`
 * job als veiligheidsnet zodat de post alsnog een afspeelbare versie krijgt
 * — zij het zonder adaptive bitrate.
 */
class SubmitVideoToFileFlux implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public int $backoff = 30;

    public int $timeout = 60;

    public function __construct(public PostMedia $postMedia) {}

    public function handle(): void
    {
        if ($this->postMedia->status !== MediaStatus::Processing) {
            return;
        }

        $disk = MediaUrl::disk();
        $sourcePath = $this->ensureOriginalArchived($disk);

        if (! $disk->exists($sourcePath)) {
            throw new RuntimeException("Source missing on disk: {$sourcePath}");
        }

        $userId = $this->postMedia->post->user_id;
        $mediaId = $this->postMedia->id;
        $prefix = "users/{$userId}/posts/hls/{$mediaId}/";

        $ladder = config('services.fileflux.ladder');

        $response = (new FileFlux)
            ->project(config('services.fileflux.project_id'))
            ->webhook(config('services.fileflux.callback_url'))
            ->workflow('ConvertVideoToHlsWorkflow')
            ->source($sourcePath)
            ->target([
                'prefix' => $prefix,
                'master_filename' => 'master.m3u8',
                'segment_duration' => $ladder['segment_duration'] ?? 6,
                'codec' => $ladder['codec'] ?? 'h264',
                'renditions' => $ladder['renditions'],
                'audio' => $ladder['audio'] ?? null,
                'poster' => $ladder['poster'] ?? null,
                'metadata' => [
                    'post_media_id' => $mediaId,
                ],
            ])
            ->convert();

        if (! $response->successful()) {
            Log::warning('SubmitVideoToFileFlux: FileFlux rejected job', [
                'post_media_id' => $mediaId,
                'status' => $response->status(),
                'body' => $response->json(),
            ]);

            throw new RuntimeException('FileFlux returned non-2xx: '.$response->status());
        }

        $taskId = $response->json('task_id') ?? $response->json('id');

        if (! is_string($taskId) || $taskId === '') {
            throw new RuntimeException('FileFlux response missing task_id');
        }

        $this->postMedia->update([
            'external_job_id' => $taskId,
            'processing_started_at' => now(),
        ]);
    }

    public function failed(?\Throwable $exception): void
    {
        Log::error('SubmitVideoToFileFlux permanently failed; dispatching local fallback', [
            'post_media_id' => $this->postMedia->id,
            'message' => $exception?->getMessage(),
        ]);

        // Fall back op lokale ffmpeg-transcode zodat de post alsnog Ready wordt.
        // De bestaande TranscodeVideo job markeert PostMedia Ready ook bij
        // ffmpeg-fout (origineel blijft afspeelbaar), dus dit is een safety net.
        TranscodeVideo::dispatch($this->postMedia);
    }

    /**
     * Verplaats het temp-upload bestand naar de definitieve `originals/`
     * locatie. Idempotent: als het al onder `/originals/` staat, blijft het
     * staan en geven we dat pad terug.
     */
    protected function ensureOriginalArchived(Filesystem $disk): string
    {
        $currentPath = $this->postMedia->path;

        if (str_contains($currentPath, '/originals/')) {
            // Idempotent: bij retry zit het al onder /originals/. We zetten
            // `original_path` als die nog leeg is zodat de resource hem altijd
            // kan vinden ongeacht of we via deze branch of de archive-branch
            // kwamen.
            if ($this->postMedia->original_path === null) {
                $this->postMedia->update(['original_path' => $currentPath]);
            }

            return $currentPath;
        }

        $userId = $this->postMedia->post->user_id;
        $extension = pathinfo($currentPath, PATHINFO_EXTENSION) ?: 'mp4';
        $archivedPath = "users/{$userId}/originals/posts/".Str::random(40).'.'.$extension;

        UserStorage::trackDelete($currentPath, $disk);
        $disk->move($currentPath, $archivedPath);
        UserStorage::trackPut($archivedPath, $disk);

        // `path` blijft tijdelijk naar het origineel wijzen tot
        // ProcessFileFluxCallback 'm overschrijft met master.m3u8. We slaan
        // het origineel-pad apart op `original_path` zodat de resource z'n
        // `original_url` correct kan genereren ook nadat `path` is geüpdatet.
        $this->postMedia->update([
            'path' => $archivedPath,
            'original_path' => $archivedPath,
        ]);

        return $archivedPath;
    }
}
