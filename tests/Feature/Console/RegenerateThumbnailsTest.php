<?php

use App\Models\Person;
use App\Models\Post;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function seedAvatarThumbnail(User $user, int $size = 150): string
{
    $disk = Storage::disk('public');

    $avatarFile = UploadedFile::fake()->image('avatar.jpg', 500, 500);
    $avatarPath = "users/{$user->id}/avatars/avatar.jpg";
    $disk->putFileAs("users/{$user->id}/avatars", $avatarFile, 'avatar.jpg');

    $thumbFile = UploadedFile::fake()->image('avatar-thumb.jpg', $size, $size);
    $thumbPath = "users/{$user->id}/avatars/thumbnails/old-avatar-thumb.jpg";
    $disk->putFileAs("users/{$user->id}/avatars/thumbnails", $thumbFile, 'old-avatar-thumb.jpg');

    $user->forceFill([
        'avatar' => $avatarPath,
        'avatar_thumbnail' => $thumbPath,
    ])->save();

    return $thumbPath;
}

function seedPostThumbnails(User $user, int $smallSize = 150, int $largeSize = 400): array
{
    $disk = Storage::disk('public');

    $mediaFile = UploadedFile::fake()->image('post.jpg', 1200, 1200);
    $mediaPath = "users/{$user->id}/posts/post.jpg";
    $disk->putFileAs("users/{$user->id}/posts", $mediaFile, 'post.jpg');

    $smallFile = UploadedFile::fake()->image('small.jpg', $smallSize, $smallSize);
    $smallPath = "users/{$user->id}/posts/thumbnails/old-small.jpg";
    $disk->putFileAs("users/{$user->id}/posts/thumbnails", $smallFile, 'old-small.jpg');

    $largeFile = UploadedFile::fake()->image('large.jpg', $largeSize, $largeSize);
    $largePath = "users/{$user->id}/posts/thumbnails/old-large.jpg";
    $disk->putFileAs("users/{$user->id}/posts/thumbnails", $largeFile, 'old-large.jpg');

    $post = Post::factory()->create([
        'user_id' => $user->id,
        'media_type' => 'image',
        'media_url' => $mediaPath,
        'thumbnail_url' => $largePath,
        'thumbnail_small_url' => $smallPath,
    ]);

    return [$post, $smallPath, $largePath];
}

it('regenerates post thumbnails at the new size and removes old files', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    [$post, $oldSmall, $oldLarge] = seedPostThumbnails($user);

    $this->artisan('thumbnails:regenerate', ['--posts' => true])->assertSuccessful();

    $post->refresh();

    expect($post->thumbnail_small_url)->not->toBe($oldSmall)
        ->and($post->thumbnail_url)->not->toBe($oldLarge);

    $disk = Storage::disk('public');
    $disk->assertMissing($oldSmall);
    $disk->assertMissing($oldLarge);
    $disk->assertExists($post->thumbnail_small_url);
    $disk->assertExists($post->thumbnail_url);

    [$w, $h] = getimagesize($disk->path($post->thumbnail_small_url));
    expect($w)->toBe(300)->and($h)->toBe(300);

    [$w, $h] = getimagesize($disk->path($post->thumbnail_url));
    expect($w)->toBe(800)->and($h)->toBe(800);
});

it('regenerates user avatar thumbnails at the new size', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $oldThumb = seedAvatarThumbnail($user);

    $this->artisan('thumbnails:regenerate', ['--avatars' => true])->assertSuccessful();

    $user->refresh();

    expect($user->avatar_thumbnail)->not->toBe($oldThumb);

    $disk = Storage::disk('public');
    $disk->assertMissing($oldThumb);
    $disk->assertExists($user->avatar_thumbnail);

    [$w, $h] = getimagesize($disk->path($user->avatar_thumbnail));
    expect($w)->toBe(300)->and($h)->toBe(300);
});

it('regenerates person avatar thumbnails at the new size', function () {
    Storage::fake('public');

    $owner = User::factory()->create();
    $disk = Storage::disk('public');

    $avatarFile = UploadedFile::fake()->image('p.jpg', 500, 500);
    $avatarPath = "users/{$owner->id}/person-avatars/p.jpg";
    $disk->putFileAs("users/{$owner->id}/person-avatars", $avatarFile, 'p.jpg');

    $oldThumbFile = UploadedFile::fake()->image('p-thumb.jpg', 150, 150);
    $oldThumbPath = "users/{$owner->id}/person-avatars/thumbnails/old.jpg";
    $disk->putFileAs("users/{$owner->id}/person-avatars/thumbnails", $oldThumbFile, 'old.jpg');

    $person = Person::factory()->create([
        'created_by_user_id' => $owner->id,
        'avatar' => $avatarPath,
        'avatar_thumbnail' => $oldThumbPath,
    ]);

    $this->artisan('thumbnails:regenerate', ['--people' => true])->assertSuccessful();

    $person->refresh();

    expect($person->avatar_thumbnail)->not->toBe($oldThumbPath);

    $disk->assertMissing($oldThumbPath);
    $disk->assertExists($person->avatar_thumbnail);

    [$w, $h] = getimagesize($disk->path($person->avatar_thumbnail));
    expect($w)->toBe(300)->and($h)->toBe(300);
});

it('does not modify storage or db on --dry-run', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    [$post, $oldSmall, $oldLarge] = seedPostThumbnails($user);

    $this->artisan('thumbnails:regenerate', ['--posts' => true, '--dry-run' => true])->assertSuccessful();

    $post->refresh();

    expect($post->thumbnail_small_url)->toBe($oldSmall)
        ->and($post->thumbnail_url)->toBe($oldLarge);

    Storage::disk('public')->assertExists($oldSmall);
    Storage::disk('public')->assertExists($oldLarge);
});

it('skips posts whose source media is missing', function () {
    Storage::fake('public');

    $user = User::factory()->create();

    $post = Post::factory()->create([
        'user_id' => $user->id,
        'media_type' => 'image',
        'media_url' => "users/{$user->id}/posts/missing.jpg",
        'thumbnail_url' => "users/{$user->id}/posts/thumbnails/old-large.jpg",
        'thumbnail_small_url' => "users/{$user->id}/posts/thumbnails/old-small.jpg",
    ]);

    $this->artisan('thumbnails:regenerate', ['--posts' => true])->assertSuccessful();

    $post->refresh();

    expect($post->thumbnail_url)->toBe("users/{$user->id}/posts/thumbnails/old-large.jpg")
        ->and($post->thumbnail_small_url)->toBe("users/{$user->id}/posts/thumbnails/old-small.jpg");
});
