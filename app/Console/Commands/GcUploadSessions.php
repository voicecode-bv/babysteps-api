<?php

namespace App\Console\Commands;

use App\Http\Controllers\Api\UploadController;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

#[Signature('uploads:gc-sessions {--hours=24 : Sessions older than this are deleted}')]
#[Description('Delete chunked upload sessions older than the given age.')]
class GcUploadSessions extends Command
{
    public function handle(): int
    {
        $hours = max(1, (int) $this->option('hours'));
        $threshold = now()->subHours($hours)->getTimestamp();

        $base = UploadController::sessionsDirectory();

        if (! is_dir($base)) {
            return self::SUCCESS;
        }

        $deleted = 0;

        foreach (File::directories($base) as $directory) {
            $meta = $directory.'/meta.json';
            $referenceTime = file_exists($meta) ? filemtime($meta) : filemtime($directory);

            if ($referenceTime !== false && $referenceTime < $threshold) {
                File::deleteDirectory($directory);
                $deleted++;
            }
        }

        $this->info("Deleted {$deleted} stale upload session(s).");

        return self::SUCCESS;
    }
}
