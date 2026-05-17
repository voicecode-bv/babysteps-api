<?php

use App\Enums\NotificationPreference;
use App\Models\Like;
use App\Models\Post;
use App\Models\User;
use App\Notifications\PostLiked;
use Illuminate\Support\Facades\Notification;
use NotificationChannels\Fcm\FcmChannel;

it('lists users who liked a post, newest first', function () {
    $post = Post::factory()->create();
    $older = User::factory()->create(['name' => 'Older Liker']);
    $newer = User::factory()->create(['name' => 'Newer Liker']);
    $viewer = User::factory()->create();
    shareCircle($post, $viewer, $older, $newer);

    Like::factory()->create([
        'likeable_id' => $post->id,
        'likeable_type' => Post::class,
        'user_id' => $older->id,
        'created_at' => now()->subMinute(),
    ]);
    Like::factory()->create([
        'likeable_id' => $post->id,
        'likeable_type' => Post::class,
        'user_id' => $newer->id,
        'created_at' => now(),
    ]);

    $response = $this->actingAs($viewer)
        ->getJson("/api/posts/{$post->id}/likes")
        ->assertOk()
        ->assertJsonPath('meta.total', 2)
        ->assertJsonCount(2, 'data');

    expect($response->json('data.0.id'))->toBe($newer->id);
    expect($response->json('data.1.id'))->toBe($older->id);
    expect($response->json('data.0'))->toHaveKeys(['id', 'is_visible', 'name', 'username', 'avatar']);
    expect($response->json('data.0.is_visible'))->toBeTrue();
});

it('returns placeholders for likers in non-shared circles when client supports v2', function () {
    $post = Post::factory()->create();
    $viewer = User::factory()->create();
    $insider = User::factory()->create(['name' => 'Visible Liker']);
    $outsider = User::factory()->create(['name' => 'Hidden Liker']);
    shareCircle($post, $viewer, $insider);
    shareCircle($post, $outsider);

    Like::factory()->create([
        'likeable_id' => $post->id,
        'likeable_type' => Post::class,
        'user_id' => $outsider->id,
        'created_at' => now()->subMinute(),
    ]);
    Like::factory()->create([
        'likeable_id' => $post->id,
        'likeable_type' => Post::class,
        'user_id' => $insider->id,
        'created_at' => now(),
    ]);

    $response = $this->actingAs($viewer)
        ->withHeaders(['X-App-Version' => '1.1.0'])
        ->getJson("/api/posts/{$post->id}/likes")
        ->assertOk()
        ->assertJsonPath('meta.total', 2)
        ->assertJsonCount(2, 'data');

    expect($response->json('data.0.id'))->toBe($insider->id);
    expect($response->json('data.0.is_visible'))->toBeTrue();
    expect($response->json('data.0.name'))->toBe('Visible Liker');

    expect($response->json('data.1.id'))->toBe($outsider->id);
    expect($response->json('data.1.is_visible'))->toBeFalse();
    expect($response->json('data.1'))->not->toHaveKey('name');
    expect($response->json('data.1'))->not->toHaveKey('username');
});

it('filters out non-shared likers entirely for old clients without the version header', function () {
    $post = Post::factory()->create();
    $viewer = User::factory()->create();
    $insider = User::factory()->create();
    $outsider = User::factory()->create();
    shareCircle($post, $viewer, $insider);
    shareCircle($post, $outsider);

    Like::factory()->create([
        'likeable_id' => $post->id,
        'likeable_type' => Post::class,
        'user_id' => $outsider->id,
    ]);
    Like::factory()->create([
        'likeable_id' => $post->id,
        'likeable_type' => Post::class,
        'user_id' => $insider->id,
    ]);

    $response = $this->actingAs($viewer)
        ->getJson("/api/posts/{$post->id}/likes")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('meta.total', 1);

    expect($response->json('data.0.id'))->toBe($insider->id);
    expect($response->json('data.0.is_visible'))->toBeTrue();
});

it('lets the post owner see all likers as visible', function () {
    $owner = User::factory()->create();
    $post = Post::factory()->create(['user_id' => $owner->id]);
    $likerA = User::factory()->create();
    $likerB = User::factory()->create();
    shareCircleOwnedBy($post, $owner, $likerA);
    shareCircleOwnedBy($post, $owner, $likerB);

    Like::factory()->create([
        'likeable_id' => $post->id,
        'likeable_type' => Post::class,
        'user_id' => $likerA->id,
    ]);
    Like::factory()->create([
        'likeable_id' => $post->id,
        'likeable_type' => Post::class,
        'user_id' => $likerB->id,
    ]);

    $response = $this->actingAs($owner)
        ->withHeaders(['X-App-Version' => '1.1.0'])
        ->getJson("/api/posts/{$post->id}/likes")
        ->assertOk()
        ->assertJsonCount(2, 'data');

    foreach ($response->json('data') as $entry) {
        expect($entry['is_visible'])->toBeTrue();
    }
});

it('requires authentication to list likes', function () {
    $post = Post::factory()->create();

    $this->getJson("/api/posts/{$post->id}/likes")
        ->assertUnauthorized();
});

it('cannot like own post', function () {
    $user = User::factory()->create();
    $post = Post::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->postJson("/api/posts/{$post->id}/like")
        ->assertForbidden();

    $this->assertDatabaseMissing('likes', [
        'likeable_id' => $post->id,
        'likeable_type' => Post::class,
    ]);
});

it('can like another users post', function () {
    $post = Post::factory()->create();
    $liker = User::factory()->create();
    shareCircle($post, $liker);

    $this->actingAs($liker)
        ->postJson("/api/posts/{$post->id}/like")
        ->assertCreated()
        ->assertJsonPath('liked', true)
        ->assertJsonPath('likes_count', 1);
});

it('sends a push notification when the post owner has a device token', function () {
    Notification::fake();

    $preferences = NotificationPreference::defaults();
    $preferences['post_liked'] = true;

    $owner = User::factory()->create(['notification_preferences' => $preferences]);
    $owner->deviceTokens()->create(['token' => 'test-token', 'last_used_at' => now()]);
    $post = Post::factory()->create(['user_id' => $owner->id]);
    $liker = User::factory()->create();
    shareCircle($post, $liker);

    $this->actingAs($liker)
        ->postJson("/api/posts/{$post->id}/like")
        ->assertCreated();

    Notification::assertSentTo(
        $owner,
        PostLiked::class,
        fn (PostLiked $notification) => in_array(FcmChannel::class, $notification->via($owner), true),
    );
});

it('does not include the fcm channel when the post owner has no device tokens', function () {
    Notification::fake();

    $owner = User::factory()->create();
    $post = Post::factory()->create(['user_id' => $owner->id]);
    $liker = User::factory()->create();
    shareCircle($post, $liker);

    $this->actingAs($liker)
        ->postJson("/api/posts/{$post->id}/like")
        ->assertCreated();

    Notification::assertSentTo(
        $owner,
        PostLiked::class,
        fn (PostLiked $notification) => ! in_array(FcmChannel::class, $notification->via($owner), true),
    );
});
