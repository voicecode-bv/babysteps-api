<?php

use App\Models\Post;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

function seedRotatedPostFromFixture(User $user): Post
{
    $disk = Storage::disk('public');

    $filename = Str::random(40).'.jpg';
    $originalPath = "users/{$user->id}/originals/posts/{$filename}";
    $displayPath = "users/{$user->id}/posts/{$filename}";

    $bytes = file_get_contents(__DIR__.'/../../fixtures/photo-orientation-6.jpg');

    // Original keeps EXIF Orientation=6. The display copy is the same bytes
    // because that's exactly the bug we're fixing: pre-fix display variants
    // were saved with un-applied orientation.
    $disk->put($originalPath, $bytes);
    $disk->put($displayPath, $bytes);

    return Post::factory()->create([
        'user_id' => $user->id,
        'media_type' => 'image',
        'media_url' => $displayPath,
        'thumbnail_url' => null,
        'thumbnail_small_url' => null,
    ]);
}

it('regenerates display and thumbnails from the original with applied orientation', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $post = seedRotatedPostFromFixture($user);

    $oldDisplayPath = $post->media_url;

    $this->artisan('posts:reorient-media')->assertSuccessful();

    $post->refresh();
    $disk = Storage::disk('public');

    expect($post->media_url)->not->toBe($oldDisplayPath)
        ->and($post->thumbnail_url)->not->toBeNull()
        ->and($post->thumbnail_small_url)->not->toBeNull();

    $disk->assertMissing($oldDisplayPath);
    $disk->assertExists($post->media_url);
    $disk->assertExists($post->thumbnail_url);
    $disk->assertExists($post->thumbnail_small_url);

    [$width, $height] = getimagesize($disk->path($post->media_url));
    expect($width)->toBe(100)
        ->and($height)->toBe(200);

    $exif = @exif_read_data($disk->path($post->media_url), 'IFD0', true);
    expect($exif['IFD0']['Orientation'] ?? 1)->toBe(1);
});

it('does nothing on dry-run', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $post = seedRotatedPostFromFixture($user);

    $originalDisplayPath = $post->media_url;

    $this->artisan('posts:reorient-media', ['--dry-run' => true])->assertSuccessful();

    $post->refresh();

    expect($post->media_url)->toBe($originalDisplayPath)
        ->and($post->thumbnail_url)->toBeNull()
        ->and($post->thumbnail_small_url)->toBeNull();

    Storage::disk('public')->assertExists($originalDisplayPath);
});

it('skips posts when the original is missing', function () {
    Storage::fake('public');

    $user = User::factory()->create();

    $post = Post::factory()->create([
        'user_id' => $user->id,
        'media_type' => 'image',
        'media_url' => "users/{$user->id}/posts/missing.jpg",
    ]);

    $this->artisan('posts:reorient-media')->assertSuccessful();

    $post->refresh();
    expect($post->media_url)->toBe("users/{$user->id}/posts/missing.jpg");
});

it('skips already-oriented originals when --only-affected is set', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $disk = Storage::disk('public');

    $filename = Str::random(40).'.jpg';
    $originalPath = "users/{$user->id}/originals/posts/{$filename}";
    $displayPath = "users/{$user->id}/posts/{$filename}";

    // photo-with-exif.jpg has no Orientation tag, so --only-affected should skip it.
    $bytes = file_get_contents(__DIR__.'/../../fixtures/photo-with-exif.jpg');
    $disk->put($originalPath, $bytes);
    $disk->put($displayPath, $bytes);

    $post = Post::factory()->create([
        'user_id' => $user->id,
        'media_type' => 'image',
        'media_url' => $displayPath,
        'thumbnail_url' => null,
        'thumbnail_small_url' => null,
    ]);

    $this->artisan('posts:reorient-media', ['--only-affected' => true])->assertSuccessful();

    $post->refresh();

    expect($post->media_url)->toBe($displayPath)
        ->and($post->thumbnail_url)->toBeNull();
});
