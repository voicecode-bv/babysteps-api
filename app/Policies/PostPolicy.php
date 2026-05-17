<?php

namespace App\Policies;

use App\Models\Post;
use App\Models\User;

class PostPolicy
{
    /**
     * Een viewer ziet een post als die de eigenaar is, of als ze minstens
     * één circle delen — als owner van die circle of als member. Dit is
     * dezelfde regel die het feed-endpoint en PostViewerVisibility
     * hanteren, zodat notification-deeplinks (`GET /api/posts/{post}`)
     * niet meer kunnen omzeilen wat het feed wel filtert.
     */
    public function view(User $user, Post $post): bool
    {
        if ($user->id === $post->user_id) {
            return true;
        }

        return $post->circles()
            ->where(function ($q) use ($user) {
                $q->where('circles.user_id', $user->id)
                    ->orWhereHas('members', fn ($m) => $m->where('users.id', $user->id));
            })
            ->exists();
    }

    public function update(User $user, Post $post): bool
    {
        return $user->id === $post->user_id;
    }

    public function delete(User $user, Post $post): bool
    {
        return $user->id === $post->user_id;
    }
}
