<?php

namespace App\Console\Commands;

use App\Models\Post;
use App\Services\MediaUploadService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('posts:backfill-thumbnails {--chunk=200 : Number of posts to process per chunk}')]
#[Description('Generate 400x400 thumbnails for image posts that do not have one yet.')]
class BackfillPostThumbnails extends Command
{
    public function handle(MediaUploadService $media): int
    {
        $query = Post::query()
            ->where('media_type', 'image')
            ->whereNull('thumbnail_url')
            ->whereNotNull('media_url');

        $total = $query->count();

        if ($total === 0) {
            $this->info('No image posts without a thumbnail. Nothing to do.');

            return self::SUCCESS;
        }

        $this->info("Backfilling thumbnails for {$total} image posts...");

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $generated = 0;
        $skipped = 0;

        $query->chunkById((int) $this->option('chunk'), function ($posts) use ($media, $bar, &$generated, &$skipped) {
            foreach ($posts as $post) {
                $thumbnailPath = $media->generateImageThumbnailFromPath(
                    $post->media_url, $post->user_id, 'posts',
                );

                if ($thumbnailPath === null) {
                    $skipped++;
                } else {
                    $post->forceFill(['thumbnail_url' => $thumbnailPath])->save();
                    $generated++;
                }

                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine(2);

        $this->info("Generated: {$generated}");

        if ($skipped > 0) {
            $this->warn("Skipped (source missing or decode failed): {$skipped}");
        }

        return self::SUCCESS;
    }
}
