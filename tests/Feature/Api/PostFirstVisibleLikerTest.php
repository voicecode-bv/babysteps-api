<?php

use App\Models\Like;
use App\Models\Post;
use App\Models\User;

it('exposes first_visible_liker on the post resource for the most recent visible liker', function () {
    $owner = User::factory()->create();
    $viewer = User::factory()->create();
    $post = Post::factory()->create(['user_id' => $owner->id]);

    $oldLiker = User::factory()->create(['name' => 'Old Visible Liker']);
    $newLiker = User::factory()->create(['name' => 'New Visible Liker']);

    shareCircle($post, $viewer, $oldLiker, $newLiker);

    Like::factory()->create([
        'likeable_id' => $post->id,
        'likeable_type' => Post::class,
        'user_id' => $oldLiker->id,
        'created_at' => now()->subMinute(),
    ]);
    Like::factory()->create([
        'likeable_id' => $post->id,
        'likeable_type' => Post::class,
        'user_id' => $newLiker->id,
        'created_at' => now(),
    ]);

    $this->actingAs($viewer)
        ->getJson("/api/posts/{$post->id}")
        ->assertOk()
        ->assertJsonPath('data.first_visible_liker.id', $newLiker->id)
        ->assertJsonPath('data.first_visible_liker.name', 'New Visible Liker');
});

it('skips hidden likers when selecting first_visible_liker', function () {
    $owner = User::factory()->create();
    $viewer = User::factory()->create();
    $post = Post::factory()->create(['user_id' => $owner->id]);

    $hiddenLiker = User::factory()->create();
    $visibleLiker = User::factory()->create(['name' => 'Visible Liker']);

    shareCircle($post, $viewer, $visibleLiker);
    shareCircle($post, $hiddenLiker);

    // Hidden user liked most recently — viewer must not see them as the
    // first visible liker, the older visible one wins.
    Like::factory()->create([
        'likeable_id' => $post->id,
        'likeable_type' => Post::class,
        'user_id' => $visibleLiker->id,
        'created_at' => now()->subMinute(),
    ]);
    Like::factory()->create([
        'likeable_id' => $post->id,
        'likeable_type' => Post::class,
        'user_id' => $hiddenLiker->id,
        'created_at' => now(),
    ]);

    $this->actingAs($viewer)
        ->getJson("/api/posts/{$post->id}")
        ->assertOk()
        ->assertJsonPath('data.first_visible_liker.id', $visibleLiker->id);
});

it('returns null first_visible_liker when no liker is visible to the viewer', function () {
    $owner = User::factory()->create();
    $viewer = User::factory()->create();
    $post = Post::factory()->create(['user_id' => $owner->id]);

    $hiddenLiker = User::factory()->create();
    shareCircle($post, $viewer); // viewer is in a different circle than the liker
    shareCircle($post, $hiddenLiker);

    Like::factory()->create([
        'likeable_id' => $post->id,
        'likeable_type' => Post::class,
        'user_id' => $hiddenLiker->id,
    ]);

    $this->actingAs($viewer)
        ->getJson("/api/posts/{$post->id}")
        ->assertOk()
        ->assertJsonPath('data.first_visible_liker', null)
        ->assertJsonPath('data.likes_count', 1);
});

it('returns null first_visible_liker when post has no likes', function () {
    $viewer = User::factory()->create();
    $post = Post::factory()->create();
    shareCircle($post, $viewer);

    $this->actingAs($viewer)
        ->getJson("/api/posts/{$post->id}")
        ->assertOk()
        ->assertJsonPath('data.first_visible_liker', null);
});

it('exposes first_visible_liker on the feed', function () {
    $viewer = User::factory()->create();
    $post = Post::factory()->create();
    $liker = User::factory()->create(['name' => 'Liker']);

    shareCircle($post, $viewer, $liker);

    Like::factory()->create([
        'likeable_id' => $post->id,
        'likeable_type' => Post::class,
        'user_id' => $liker->id,
    ]);

    $this->actingAs($viewer)
        ->getJson('/api/feed')
        ->assertOk()
        ->assertJsonPath('data.0.first_visible_liker.id', $liker->id);
});
