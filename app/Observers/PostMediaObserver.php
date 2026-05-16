<?php

namespace App\Observers;

use App\Models\Post;
use App\Models\PostMedia;

/**
 * Keeps the legacy shadow columns on `posts` in sync with the first media
 * item (sort_order = 0). Lets old clients keep reading `media_url` /
 * `media_type` / etc. while new clients use the `media[]` relation.
 *
 * Only mutates the post when sort_order = 0 (the representative item). Any
 * change to that primary item — initial insert, transcode-job update,
 * deletion that promotes the next item to position 0 — propagates here.
 */
class PostMediaObserver
{
    public function saved(PostMedia $postMedia): void
    {
        if ($postMedia->sort_order !== 0) {
            return;
        }

        $this->syncShadowFrom($postMedia);
    }

    public function deleted(PostMedia $postMedia): void
    {
        if ($postMedia->sort_order !== 0) {
            return;
        }

        $next = PostMedia::query()
            ->where('post_id', $postMedia->post_id)
            ->where('id', '!=', $postMedia->id)
            ->orderBy('sort_order')
            ->first();

        if ($next === null) {
            return;
        }

        $this->syncShadowFrom($next);
    }

    private function syncShadowFrom(PostMedia $media): void
    {
        // Go through the model (not query builder) so the spatial Point cast
        // serializes `coordinates` correctly via ST_GeogFromText.
        $post = Post::query()->find($media->post_id);

        $post?->update([
            'media_url' => $media->path,
            'media_type' => $media->type,
            'media_status' => $media->status,
            'thumbnail_url' => $media->thumbnail_path,
            'thumbnail_small_url' => $media->thumbnail_small_path,
            'taken_at' => $media->taken_at,
            'coordinates' => $media->coordinates,
        ]);
    }
}
