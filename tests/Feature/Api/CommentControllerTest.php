<?php

use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use App\Notifications\PostCommented;
use Illuminate\Support\Facades\Notification;

it('returns paginated comments for a post, oldest first', function () {
    $post = Post::factory()->create();

    $oldest = Comment::factory()->create([
        'post_id' => $post->id,
        'created_at' => now()->subMinutes(10),
    ]);
    $newest = Comment::factory()->create([
        'post_id' => $post->id,
        'created_at' => now(),
    ]);

    $this->actingAs(User::factory()->create())
        ->getJson("/api/posts/{$post->id}/comments")
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', $oldest->id)
        ->assertJsonPath('data.1.id', $newest->id)
        ->assertJsonStructure([
            'data' => [
                ['id', 'body', 'created_at', 'user' => ['id']],
            ],
            'links',
            'meta' => ['current_page', 'last_page', 'per_page', 'total'],
        ]);
});

it('paginates comments at 20 per page', function () {
    $post = Post::factory()->create();
    Comment::factory()->count(25)->create(['post_id' => $post->id]);

    $this->actingAs(User::factory()->create())
        ->getJson("/api/posts/{$post->id}/comments")
        ->assertOk()
        ->assertJsonCount(20, 'data')
        ->assertJsonPath('meta.total', 25)
        ->assertJsonPath('meta.last_page', 2);

    $this->actingAs(User::factory()->create())
        ->getJson("/api/posts/{$post->id}/comments?page=2")
        ->assertOk()
        ->assertJsonCount(5, 'data');
});

it('requires authentication to list comments', function () {
    $post = Post::factory()->create();

    $this->getJson("/api/posts/{$post->id}/comments")
        ->assertUnauthorized();
});

it('returns not found when listing comments for a non-existent post', function () {
    $this->actingAs(User::factory()->create())
        ->getJson('/api/posts/99999/comments')
        ->assertNotFound();
});

it('reflects is_liked on listed comments for the authenticated user', function () {
    $user = User::factory()->create();
    $post = Post::factory()->create();
    $comment = Comment::factory()->create(['post_id' => $post->id]);
    $comment->likes()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->getJson("/api/posts/{$post->id}/comments")
        ->assertOk()
        ->assertJsonPath('data.0.is_liked', true);

    $this->actingAs(User::factory()->create())
        ->getJson("/api/posts/{$post->id}/comments")
        ->assertOk()
        ->assertJsonPath('data.0.is_liked', false);
});

it('can store a comment on a post', function () {
    $user = User::factory()->create();
    $post = Post::factory()->create();

    $this->actingAs($user)
        ->postJson("/api/posts/{$post->id}/comments", [
            'body' => 'Great post!',
        ])
        ->assertCreated()
        ->assertJsonPath('data.body', 'Great post!')
        ->assertJsonStructure([
            'data' => [
                'id', 'body', 'created_at', 'updated_at',
                'user' => ['id', 'name', 'username', 'avatar'],
            ],
        ]);

    $this->assertDatabaseHas('comments', [
        'user_id' => $user->id,
        'post_id' => $post->id,
        'body' => 'Great post!',
    ]);
});

it('validates comment body is required', function () {
    $post = Post::factory()->create();

    $this->actingAs(User::factory()->create())
        ->postJson("/api/posts/{$post->id}/comments", [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('body');
});

it('validates comment body max length', function () {
    $post = Post::factory()->create();

    $this->actingAs(User::factory()->create())
        ->postJson("/api/posts/{$post->id}/comments", [
            'body' => str_repeat('a', 1001),
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('body');
});

it('requires authentication to store a comment', function () {
    $post = Post::factory()->create();

    $this->postJson("/api/posts/{$post->id}/comments", ['body' => 'Hello'])
        ->assertUnauthorized();
});

it('throttles comment creation', function () {
    $user = User::factory()->create();
    $post = Post::factory()->create();

    $this->actingAs($user);

    foreach (range(1, 30) as $i) {
        $this->postJson("/api/posts/{$post->id}/comments", ['body' => "Comment {$i}"])
            ->assertCreated();
    }

    $this->postJson("/api/posts/{$post->id}/comments", ['body' => 'Too many'])
        ->assertStatus(429);
});

it('returns not found for commenting on non-existent post', function () {
    $this->actingAs(User::factory()->create())
        ->postJson('/api/posts/99999/comments', ['body' => 'Hello'])
        ->assertNotFound();
});

it('can delete own comment', function () {
    $user = User::factory()->create();
    $comment = Comment::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->deleteJson("/api/comments/{$comment->id}")
        ->assertNoContent();

    $this->assertDatabaseMissing('comments', ['id' => $comment->id]);
});

it('post owner can delete any comment on their post', function () {
    $postOwner = User::factory()->create();
    $post = Post::factory()->create(['user_id' => $postOwner->id]);
    $comment = Comment::factory()->create(['post_id' => $post->id]);

    $this->actingAs($postOwner)
        ->deleteJson("/api/comments/{$comment->id}")
        ->assertNoContent();

    $this->assertDatabaseMissing('comments', ['id' => $comment->id]);
});

it('cannot delete another users comment on someone elses post', function () {
    $comment = Comment::factory()->create();

    $this->actingAs(User::factory()->create())
        ->deleteJson("/api/comments/{$comment->id}")
        ->assertForbidden();
});

it('requires authentication to delete a comment', function () {
    $comment = Comment::factory()->create();

    $this->deleteJson("/api/comments/{$comment->id}")
        ->assertUnauthorized();
});

it('notifies the post owner when a comment is added', function () {
    Notification::fake();

    $postOwner = User::factory()->create();
    $post = Post::factory()->create(['user_id' => $postOwner->id]);
    $commenter = User::factory()->create();

    $this->actingAs($commenter)
        ->postJson("/api/posts/{$post->id}/comments", ['body' => 'Hi!'])
        ->assertCreated();

    Notification::assertSentTo($postOwner, PostCommented::class);
});

it('does not notify the post owner when they comment on their own post', function () {
    Notification::fake();

    $postOwner = User::factory()->create();
    $post = Post::factory()->create(['user_id' => $postOwner->id]);

    $this->actingAs($postOwner)
        ->postJson("/api/posts/{$post->id}/comments", ['body' => 'Self note'])
        ->assertCreated();

    Notification::assertNotSentTo($postOwner, PostCommented::class);
});
