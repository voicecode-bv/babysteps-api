<?php

namespace App\Console\Commands;

use App\Enums\MediaStatus;
use App\Jobs\TranscodeVideo;
use App\Models\PostMedia;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Detect FileFlux-jobs die langer dan de timeout in Processing hangen en
 * dispatch een lokale TranscodeVideo fallback. Voorkomt dat een verloren
 * webhook of stille FileFlux-fout een post permanent in 'processing' laat.
 */
#[Signature('media:reconcile-fileflux-jobs')]
#[Description('Mark stuck FileFlux video transcodes as Failed and dispatch local fallback')]
class ReconcileFileFluxJobs extends Command
{
    public function handle(): int
    {
        $timeoutMinutes = (int) config('services.fileflux.job_timeout_minutes', 30);
        $cutoff = now()->subMinutes($timeoutMinutes);

        $stuck = PostMedia::query()
            ->where('status', MediaStatus::Processing)
            ->whereNotNull('external_job_id')
            ->where('processing_started_at', '<', $cutoff)
            ->get();

        if ($stuck->isEmpty()) {
            $this->info('No stuck FileFlux jobs.');

            return self::SUCCESS;
        }

        foreach ($stuck as $media) {
            Log::warning('Reconciling stuck FileFlux job', [
                'post_media_id' => $media->id,
                'external_job_id' => $media->external_job_id,
                'started_at' => $media->processing_started_at?->toIso8601String(),
            ]);

            $media->update([
                'external_job_id' => null,
                'processing_started_at' => null,
            ]);

            TranscodeVideo::dispatch($media);
        }

        $this->info("Reconciled {$stuck->count()} stuck job(s); local fallback dispatched.");

        return self::SUCCESS;
    }
}
