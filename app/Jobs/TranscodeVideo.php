<?php

namespace App\Jobs;

use App\Enums\MediaStatus;
use App\Models\PostMedia;
use App\Services\MediaUploadService;
use App\Support\MediaUrl;
use App\Support\UserStorage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

class TranscodeVideo implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 600;

    public function __construct(
        public PostMedia $postMedia,
    ) {}

    public function handle(MediaUploadService $media): void
    {
        $disk = MediaUrl::disk();
        $originalPath = $this->postMedia->path;

        if (! $disk->exists($originalPath)) {
            Log::error("TranscodeVideo: source file not found for post media {$this->postMedia->id}", [
                'path' => $originalPath,
            ]);
            $this->postMedia->update(['status' => MediaStatus::Failed]);

            return;
        }

        $tempInput = tempnam(sys_get_temp_dir(), 'transcode_in_');
        file_put_contents($tempInput, $disk->get($originalPath));

        $tempOutput = tempnam(sys_get_temp_dir(), 'transcode_out_').'.mp4';

        try {
            $result = Process::timeout($this->timeout)->run([
                'ffmpeg',
                '-i', $tempInput,
                '-vf', "scale=-2:'min(1080,ih)'",
                '-c:v', 'libx264',
                '-preset', 'medium',
                '-crf', '23',
                '-pix_fmt', 'yuv420p',
                '-c:a', 'aac',
                '-b:a', '128k',
                '-movflags', '+faststart',
                '-y',
                $tempOutput,
            ]);

            if ($result->failed() || ! file_exists($tempOutput) || filesize($tempOutput) === 0) {
                Log::error("TranscodeVideo: FFmpeg failed for post media {$this->postMedia->id}", [
                    'stderr' => $result->errorOutput(),
                ]);

                // Keep the original; mark ready so the user can still view it.
                $this->postMedia->update(['status' => MediaStatus::Ready]);

                return;
            }

            $userId = $this->postMedia->post->user_id;
            $userFolder = "users/{$userId}";
            $transcodedFilename = Str::random(40).'.mp4';
            $transcodedPath = "{$userFolder}/posts/{$transcodedFilename}";

            $disk->put($transcodedPath, file_get_contents($tempOutput));
            UserStorage::trackPut($transcodedPath, $disk);

            if (! str_contains($originalPath, '/originals/')) {
                $originalExtension = pathinfo($originalPath, PATHINFO_EXTENSION) ?: 'mp4';
                $originalFilename = Str::random(40).'.'.$originalExtension;
                $archivedPath = "{$userFolder}/originals/posts/{$originalFilename}";

                $disk->move($originalPath, $archivedPath);
            }

            $this->postMedia->update([
                'path' => $transcodedPath,
                'status' => MediaStatus::Ready,
            ]);
        } catch (\Throwable $e) {
            Log::error("TranscodeVideo: exception for post media {$this->postMedia->id}", [
                'message' => $e->getMessage(),
            ]);

            $this->postMedia->update(['status' => MediaStatus::Ready]);
        } finally {
            @unlink($tempInput);
            @unlink($tempOutput);
        }
    }

    public function failed(?\Throwable $exception): void
    {
        Log::error("TranscodeVideo: job permanently failed for post media {$this->postMedia->id}", [
            'message' => $exception?->getMessage(),
        ]);

        $this->postMedia->update(['status' => MediaStatus::Ready]);
    }
}
