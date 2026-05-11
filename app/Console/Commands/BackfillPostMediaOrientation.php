<?php

namespace App\Console\Commands;

use App\Models\Post;
use App\Services\MediaUploadService;
use App\Support\MediaUrl;
use App\Support\UserStorage;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Str;
use Intervention\Image\Laravel\Facades\Image;
use Throwable;

#[Signature('posts:reorient-media {--chunk=200 : Number of posts to process per chunk} {--dry-run : Report what would change without writing} {--only-affected : Skip originals whose EXIF Orientation is already 1 or missing}')]
#[Description('Re-generate display variants and thumbnails for image posts from their originals so EXIF orientation is correctly applied. Originals stay untouched.')]
class BackfillPostMediaOrientation extends Command
{
    private const MAX_DISPLAY_WIDTH = 1920;

    private const DISPLAY_QUALITY = 85;

    public function handle(MediaUploadService $media): int
    {
        $query = Post::query()
            ->where('media_type', 'image')
            ->whereNotNull('media_url');

        $total = $query->count();

        if ($total === 0) {
            $this->info('No image posts found.');

            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');
        $onlyAffected = (bool) $this->option('only-affected');

        $this->info(($dryRun ? '[DRY RUN] ' : '')."Processing {$total} image posts...");

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $disk = MediaUrl::disk();
        $regenerated = 0;
        $skippedMissing = 0;
        $skippedAlreadyOriented = 0;
        $failed = 0;

        $query->chunkById((int) $this->option('chunk'), function ($posts) use ($media, $disk, $dryRun, $onlyAffected, $bar, &$regenerated, &$skippedMissing, &$skippedAlreadyOriented, &$failed): void {
            foreach ($posts as $post) {
                $originalPath = $this->originalPathFor($post->media_url);

                if ($originalPath === null || ! $disk->exists($originalPath)) {
                    $skippedMissing++;
                    $bar->advance();

                    continue;
                }

                if ($onlyAffected && ! $this->originalNeedsReorient($disk, $originalPath)) {
                    $skippedAlreadyOriented++;
                    $bar->advance();

                    continue;
                }

                if ($dryRun) {
                    $regenerated++;
                    $bar->advance();

                    continue;
                }

                try {
                    $newPaths = $this->regenerate($media, $disk, $originalPath, $post->user_id);

                    $oldPaths = array_filter([
                        $post->media_url,
                        $post->thumbnail_url,
                        $post->thumbnail_small_url,
                    ]);

                    $post->forceFill($newPaths)->save();

                    foreach ($oldPaths as $oldPath) {
                        if (in_array($oldPath, $newPaths, true)) {
                            continue;
                        }

                        UserStorage::trackDelete($oldPath, $disk);
                        $disk->delete($oldPath);
                    }

                    $regenerated++;
                } catch (Throwable $e) {
                    $failed++;
                    $this->newLine();
                    $this->error("Post {$post->id}: {$e->getMessage()}");
                }

                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine(2);

        $this->info(($dryRun ? '[DRY RUN] ' : '')."Regenerated: {$regenerated}");

        if ($skippedAlreadyOriented > 0) {
            $this->info("Skipped (orientation already correct): {$skippedAlreadyOriented}");
        }

        if ($skippedMissing > 0) {
            $this->warn("Skipped (original missing): {$skippedMissing}");
        }

        if ($failed > 0) {
            $this->error("Failed: {$failed}");
        }

        return self::SUCCESS;
    }

    private function originalPathFor(string $displayPath): ?string
    {
        $originalPath = preg_replace(
            '#^(users/[0-9a-f-]{36})/(?!originals/)(.+)$#',
            '$1/originals/$2',
            $displayPath,
        );

        if ($originalPath === null || $originalPath === $displayPath) {
            return null;
        }

        return $originalPath;
    }

    /**
     * @return array{media_url: string, thumbnail_url: ?string, thumbnail_small_url: ?string}
     */
    private function regenerate(MediaUploadService $media, Filesystem $disk, string $originalPath, string $userId): array
    {
        $extension = strtolower(pathinfo($originalPath, PATHINFO_EXTENSION)) ?: 'jpg';

        $tempSource = tempnam(sys_get_temp_dir(), 'reorient_src_');
        file_put_contents($tempSource, $disk->get($originalPath));

        $tempDisplay = tempnam(sys_get_temp_dir(), 'reorient_display_').'.'.$extension;

        try {
            $image = Image::decodePath($tempSource);
            $image->orient();
            $image->scaleDown(width: self::MAX_DISPLAY_WIDTH);
            $image->save($tempDisplay, quality: self::DISPLAY_QUALITY);

            $displayFilename = Str::random(40).'.'.$extension;
            $displayPath = "users/{$userId}/posts/{$displayFilename}";

            $disk->put($displayPath, file_get_contents($tempDisplay));
            UserStorage::trackPut($displayPath, $disk);

            $thumbnailPath = $media->generateImageThumbnailFromPath($originalPath, $userId, 'posts', size: 400);
            $thumbnailSmallPath = $media->generateImageThumbnailFromPath($originalPath, $userId, 'posts', size: 150);

            return [
                'media_url' => $displayPath,
                'thumbnail_url' => $thumbnailPath,
                'thumbnail_small_url' => $thumbnailSmallPath,
            ];
        } finally {
            @unlink($tempSource);
            @unlink($tempDisplay);
        }
    }

    private function originalNeedsReorient(Filesystem $disk, string $originalPath): bool
    {
        if (! function_exists('exif_read_data')) {
            return true;
        }

        $temp = tempnam(sys_get_temp_dir(), 'reorient_check_');
        file_put_contents($temp, $disk->get($originalPath));

        try {
            $data = @exif_read_data($temp, 'IFD0', true);
            $orientation = $data['IFD0']['Orientation'] ?? null;

            return is_int($orientation) && $orientation > 1;
        } catch (Throwable) {
            return true;
        } finally {
            @unlink($temp);
        }
    }
}
