<?php

use App\Models\Circle;
use App\Models\Post;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function orientationFixture(): UploadedFile
{
    return new UploadedFile(
        __DIR__.'/../../fixtures/photo-orientation-6.jpg',
        'photo.jpg',
        'image/jpeg',
        null,
        true,
    );
}

it('physically rotates iOS-style images when storing the display variant', function () {
    Storage::fake('public');
    $user = User::factory()->create();
    $circle = Circle::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->postJson('/api/posts', [
            'media' => orientationFixture(),
            'circle_ids' => [$circle->id],
        ])
        ->assertCreated();

    $post = Post::first();
    $disk = Storage::disk('public');

    $displayPath = $disk->path($post->media_url);
    [$width, $height] = getimagesize($displayPath);

    // Fixture is 200x100 with EXIF Orientation=6. After orient() the pixels
    // are physically rotated 90° CW, so the stored display variant should be
    // 100x200 (the scaleDown(1920) ceiling does not kick in at these sizes).
    expect($width)->toBe(100)
        ->and($height)->toBe(200);

    $exif = @exif_read_data($displayPath, 'IFD0', true);
    expect($exif['IFD0']['Orientation'] ?? 1)->toBe(1);
});

it('resets the EXIF Orientation tag on the generated thumbnails', function () {
    Storage::fake('public');
    $user = User::factory()->create();
    $circle = Circle::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->postJson('/api/posts', [
            'media' => orientationFixture(),
            'circle_ids' => [$circle->id],
        ])
        ->assertCreated();

    $post = Post::first();
    $disk = Storage::disk('public');

    foreach ([$post->thumbnail_url, $post->thumbnail_small_url] as $thumbPath) {
        expect($thumbPath)->not->toBeNull();

        $exif = @exif_read_data($disk->path($thumbPath), 'IFD0', true);
        expect($exif['IFD0']['Orientation'] ?? 1)->toBe(1);
    }
});

it('leaves the stored original untouched with its EXIF orientation tag preserved', function () {
    Storage::fake('public');
    $user = User::factory()->create();
    $circle = Circle::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->postJson('/api/posts', [
            'media' => orientationFixture(),
            'circle_ids' => [$circle->id],
        ])
        ->assertCreated();

    $post = Post::first();
    $disk = Storage::disk('public');

    $originalPath = $disk->path("users/{$user->id}/originals/posts/".basename($post->media_url));

    expect(file_exists($originalPath))->toBeTrue();

    [$width, $height] = getimagesize($originalPath);
    expect($width)->toBe(200)
        ->and($height)->toBe(100);

    $exif = @exif_read_data($originalPath, 'IFD0', true);
    expect($exif['IFD0']['Orientation'])->toBe(6);
});
