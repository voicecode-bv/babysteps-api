<?php

namespace App\Console\Commands;

use App\Models\Person;
use App\Models\Post;
use App\Models\User;
use App\Services\MediaUploadService;
use App\Support\MediaUrl;
use App\Support\UserStorage;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Contracts\Filesystem\Filesystem;
use Throwable;

#[Signature('thumbnails:regenerate {--chunk=200 : Number of records to process per chunk} {--dry-run : Report what would change without writing} {--posts : Only regenerate post thumbnails} {--avatars : Only regenerate user avatar thumbnails} {--people : Only regenerate person avatar thumbnails}')]
#[Description('Regenerate every existing thumbnail (post grid + large, user avatars, person avatars) at the current configured size. Deletes the old thumbnail files after writing the new ones.')]
class RegenerateThumbnails extends Command
{
    public function handle(MediaUploadService $media): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $chunk = (int) $this->option('chunk');

        $scopePosts = (bool) $this->option('posts');
        $scopeAvatars = (bool) $this->option('avatars');
        $scopePeople = (bool) $this->option('people');

        // No explicit scope flags → run everything.
        if (! $scopePosts && ! $scopeAvatars && ! $scopePeople) {
            $scopePosts = $scopeAvatars = $scopePeople = true;
        }

        $disk = MediaUrl::disk();

        if ($scopePosts) {
            $this->regeneratePostThumbnails($media, $disk, $chunk, $dryRun);
        }

        if ($scopeAvatars) {
            $this->regenerateUserAvatars($media, $disk, $chunk, $dryRun);
        }

        if ($scopePeople) {
            $this->regeneratePersonAvatars($media, $disk, $chunk, $dryRun);
        }

        return self::SUCCESS;
    }

    private function regeneratePostThumbnails(MediaUploadService $media, Filesystem $disk, int $chunk, bool $dryRun): void
    {
        $query = Post::query()
            ->where('media_type', 'image')
            ->whereNotNull('media_url')
            ->where(function ($q) {
                $q->whereNotNull('thumbnail_url')
                    ->orWhereNotNull('thumbnail_small_url');
            });

        $total = $query->count();

        if ($total === 0) {
            $this->info('No image posts with existing thumbnails to regenerate.');

            return;
        }

        $this->info(($dryRun ? '[DRY RUN] ' : '')."Regenerating thumbnails for {$total} image posts...");

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $regenerated = 0;
        $skipped = 0;
        $failed = 0;

        $query->chunkById($chunk, function ($posts) use ($media, $disk, $dryRun, $bar, &$regenerated, &$skipped, &$failed) {
            foreach ($posts as $post) {
                if (! $disk->exists($post->media_url)) {
                    $skipped++;
                    $bar->advance();

                    continue;
                }

                if ($dryRun) {
                    $regenerated++;
                    $bar->advance();

                    continue;
                }

                try {
                    $newLarge = $media->generateImageThumbnailFromPath(
                        $post->media_url,
                        $post->user_id,
                        'posts',
                        size: MediaUploadService::THUMBNAIL_SIZE_LARGE,
                    );

                    $newSmall = $media->generateImageThumbnailFromPath(
                        $post->media_url,
                        $post->user_id,
                        'posts',
                        size: MediaUploadService::THUMBNAIL_SIZE_SMALL,
                    );

                    if ($newLarge === null && $newSmall === null) {
                        $failed++;
                        $bar->advance();

                        continue;
                    }

                    $oldLarge = $post->thumbnail_url;
                    $oldSmall = $post->thumbnail_small_url;

                    $post->forceFill([
                        'thumbnail_url' => $newLarge ?? $post->thumbnail_url,
                        'thumbnail_small_url' => $newSmall ?? $post->thumbnail_small_url,
                    ])->save();

                    $this->deleteIfReplaced($disk, $oldLarge, $newLarge);
                    $this->deleteIfReplaced($disk, $oldSmall, $newSmall);

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

        $this->info(($dryRun ? '[DRY RUN] ' : '')."Posts — regenerated: {$regenerated}");

        if ($skipped > 0) {
            $this->warn("Posts — skipped (source missing): {$skipped}");
        }

        if ($failed > 0) {
            $this->error("Posts — failed: {$failed}");
        }
    }

    private function regenerateUserAvatars(MediaUploadService $media, Filesystem $disk, int $chunk, bool $dryRun): void
    {
        $query = User::query()
            ->whereNotNull('avatar')
            ->whereNotNull('avatar_thumbnail');

        $total = $query->count();

        if ($total === 0) {
            $this->info('No user avatars with existing thumbnails to regenerate.');

            return;
        }

        $this->info(($dryRun ? '[DRY RUN] ' : '')."Regenerating {$total} user avatar thumbnails...");

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $regenerated = 0;
        $skipped = 0;
        $failed = 0;

        $query->chunkById($chunk, function ($users) use ($media, $disk, $dryRun, $bar, &$regenerated, &$skipped, &$failed) {
            foreach ($users as $user) {
                if (! $disk->exists($user->avatar)) {
                    $skipped++;
                    $bar->advance();

                    continue;
                }

                if ($dryRun) {
                    $regenerated++;
                    $bar->advance();

                    continue;
                }

                try {
                    $newThumb = $media->generateImageThumbnailFromPath(
                        $user->avatar,
                        $user->id,
                        'avatars',
                        size: MediaUploadService::THUMBNAIL_SIZE_SMALL,
                    );

                    if ($newThumb === null) {
                        $failed++;
                        $bar->advance();

                        continue;
                    }

                    $oldThumb = $user->avatar_thumbnail;
                    $user->forceFill(['avatar_thumbnail' => $newThumb])->save();

                    $this->deleteIfReplaced($disk, $oldThumb, $newThumb);

                    $regenerated++;
                } catch (Throwable $e) {
                    $failed++;
                    $this->newLine();
                    $this->error("User {$user->id}: {$e->getMessage()}");
                }

                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine(2);

        $this->info(($dryRun ? '[DRY RUN] ' : '')."Avatars — regenerated: {$regenerated}");

        if ($skipped > 0) {
            $this->warn("Avatars — skipped (source missing): {$skipped}");
        }

        if ($failed > 0) {
            $this->error("Avatars — failed: {$failed}");
        }
    }

    private function regeneratePersonAvatars(MediaUploadService $media, Filesystem $disk, int $chunk, bool $dryRun): void
    {
        $query = Person::query()
            ->whereNotNull('avatar')
            ->whereNotNull('avatar_thumbnail');

        $total = $query->count();

        if ($total === 0) {
            $this->info('No person avatars with existing thumbnails to regenerate.');

            return;
        }

        $this->info(($dryRun ? '[DRY RUN] ' : '')."Regenerating {$total} person avatar thumbnails...");

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $regenerated = 0;
        $skipped = 0;
        $failed = 0;

        $query->chunkById($chunk, function ($people) use ($media, $disk, $dryRun, $bar, &$regenerated, &$skipped, &$failed) {
            foreach ($people as $person) {
                if (! $disk->exists($person->avatar)) {
                    $skipped++;
                    $bar->advance();

                    continue;
                }

                if ($dryRun) {
                    $regenerated++;
                    $bar->advance();

                    continue;
                }

                try {
                    $newThumb = $media->generateImageThumbnailFromPath(
                        $person->avatar,
                        $person->created_by_user_id,
                        'person-avatars',
                        size: MediaUploadService::THUMBNAIL_SIZE_SMALL,
                    );

                    if ($newThumb === null) {
                        $failed++;
                        $bar->advance();

                        continue;
                    }

                    $oldThumb = $person->avatar_thumbnail;
                    $person->forceFill(['avatar_thumbnail' => $newThumb])->save();

                    $this->deleteIfReplaced($disk, $oldThumb, $newThumb);

                    $regenerated++;
                } catch (Throwable $e) {
                    $failed++;
                    $this->newLine();
                    $this->error("Person {$person->id}: {$e->getMessage()}");
                }

                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine(2);

        $this->info(($dryRun ? '[DRY RUN] ' : '')."People — regenerated: {$regenerated}");

        if ($skipped > 0) {
            $this->warn("People — skipped (source missing): {$skipped}");
        }

        if ($failed > 0) {
            $this->error("People — failed: {$failed}");
        }
    }

    private function deleteIfReplaced(Filesystem $disk, ?string $oldPath, ?string $newPath): void
    {
        if ($oldPath === null || $oldPath === $newPath) {
            return;
        }

        UserStorage::trackDelete($oldPath, $disk);
        $disk->delete($oldPath);
    }
}
