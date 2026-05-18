<?php

namespace App\Jobs;

use App\Enums\MediaStatus;
use App\Models\PostMedia;
use App\Support\MediaUrl;
use App\Support\UserStorage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Handelt een FileFlux completion-webhook af: PostMedia bijwerken naar de
 * HLS master, alle nieuwe files trackken voor user storage quota, en de
 * tijdelijke single-MP4 op `users/{uid}/posts/` opruimen (de master.m3u8 +
 * variants vervangen die rol).
 */
class ProcessFileFluxCallback implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** S3 eventual consistency: outputs kunnen net na webhook nog niet zichtbaar zijn. */
    public int $tries = 3;

    public int $backoff = 30;

    public int $timeout = 120;

    /**
     * @param  array<string, mixed>  $outputs
     */
    public function __construct(
        public string $taskId,
        public string $status,
        public array $outputs,
        public ?string $errorMessage = null,
    ) {}

    public function handle(): void
    {
        /** @var PostMedia|null $media */
        $media = PostMedia::where('external_job_id', $this->taskId)->first();

        if ($media === null) {
            Log::warning('ProcessFileFluxCallback: no PostMedia for task', ['task_id' => $this->taskId]);

            return;
        }

        if ($media->status === MediaStatus::Ready) {
            // Idempotency: webhook al verwerkt, niets te doen.
            return;
        }

        if ($this->status === 'failed') {
            $this->handleFailure($media);

            return;
        }

        $this->handleSuccess($media);
    }

    protected function handleFailure(PostMedia $media): void
    {
        Log::warning('FileFlux job failed; falling back to local transcode', [
            'post_media_id' => $media->id,
            'task_id' => $this->taskId,
            'error' => $this->errorMessage,
        ]);

        // Reset external_job_id zodat de fallback niet door reconcile als
        // "stuck" wordt opgepikt; TranscodeVideo update status zelf naar Ready.
        $media->update(['external_job_id' => null]);

        TranscodeVideo::dispatch($media);
    }

    protected function handleSuccess(PostMedia $media): void
    {
        $disk = MediaUrl::disk();
        $masterPath = $this->outputs['master'] ?? null;

        if (! is_string($masterPath) || $masterPath === '') {
            throw new RuntimeException('FileFlux callback missing outputs.master');
        }

        if (! $disk->exists($masterPath)) {
            // Eventual consistency: retry job met backoff.
            throw new RuntimeException("Master playlist not on disk yet: {$masterPath}");
        }

        // Bouw lijst van nieuwe files: master + alle variants + alle segments + poster.
        $hlsDirectory = dirname($masterPath);
        $newFiles = collect($disk->allFiles($hlsDirectory));

        foreach ($newFiles as $file) {
            UserStorage::trackPut($file, $disk);
        }

        $oldPath = $media->path;
        $posterPath = is_string($this->outputs['poster'] ?? null) && $this->outputs['poster'] !== ''
            ? $this->outputs['poster']
            : $media->thumbnail_path;

        $media->update([
            'path' => $masterPath,
            'format' => 'hls',
            'status' => MediaStatus::Ready,
            'thumbnail_path' => $posterPath,
        ]);

        // Ruim het oude temp-MP4 op (alleen als het NIET in /originals/ staat
        // en NIET hetzelfde pad is als de nieuwe master — dat zou nooit het
        // geval moeten zijn, maar we zijn voorzichtig).
        if (
            $oldPath !== null
            && $oldPath !== $masterPath
            && ! str_contains($oldPath, '/originals/')
            && $disk->exists($oldPath)
        ) {
            UserStorage::trackDelete($oldPath, $disk);
            $disk->delete($oldPath);
        }
    }
}
