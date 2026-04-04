<?php

use App\Models\Like;
use App\Models\Post;
use App\Models\User;

it('returns paginated feed of posts', function () {
    Post::factory()->count(15)->create();

    $this->actingAs(User::factory()->create())
        ->getJson('/api/feed')
        ->assertSuccessful()
        ->assertJsonCount(10, 'data')
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id', 'media_url', 'media_type', 'caption', 'location',
                    'user' => ['id', 'name', 'username', 'avatar'],
                    'likes_count', 'comments_count',
                ],
            ],
            'links',
            'meta',
        ]);
});

it('returns posts in newest-first order', function () {
    $oldest = Post::factory()->create(['created_at' => now()->subDay()]);
    $newest = Post::factory()->create(['created_at' => now()]);

    $response = $this->actingAs(User::factory()->create())
        ->getJson('/api/feed')
        ->assertSuccessful();

    $ids = collect($response->json('data'))->pluck('id')->all();

    expect($ids[0])->toBe($newest->id)
        ->and($ids[1])->toBe($oldest->id);
});

it('can paginate to the second page', function () {
    Post::factory()->count(15)->create();

    $this->actingAs(User::factory()->create())
        ->getJson('/api/feed?page=2')
        ->assertSuccessful()
        ->assertJsonCount(5, 'data');
});

it('returns empty data when no posts exist', function () {
    $this->actingAs(User::factory()->create())
        ->getJson('/api/feed')
        ->assertSuccessful()
        ->assertJsonCount(0, 'data');
});

it('returns is_liked true when user has liked the post', function () {
    $user = User::factory()->create();
    $post = Post::factory()->create();
    Like::factory()->for($post, 'likeable')->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->getJson('/api/feed')
        ->assertSuccessful()
        ->assertJsonPath('data.0.is_liked', true);
});

it('returns is_liked false when user has not liked the post', function () {
    Post::factory()->create();

    $this->actingAs(User::factory()->create())
        ->getJson('/api/feed')
        ->assertSuccessful()
        ->assertJsonPath('data.0.is_liked', false);
});

it('requires authentication to view feed', function () {
    $this->getJson('/api/feed')
        ->assertUnauthorized();
});
