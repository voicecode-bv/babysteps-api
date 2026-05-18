<?php

use App\Http\Controllers\Api\UploadController;
use App\Jobs\TranscodeVideo;
use App\Models\Circle;
use App\Models\Post;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

afterEach(function () {
    File::deleteDirectory(UploadController::sessionsDirectory());
});

/**
 * @return array{0: string, 1: string} token, sessionId
 */
function uploadChunkedFakeImage(User $user, string $filename = 'photo.jpg'): array
{
    $fakeImage = UploadedFile::fake()->image($filename, 100, 100);
    $bytes = file_get_contents($fakeImage->getPathname()) ?: '';

    test()->actingAs($user);

    $uploadId = test()->postJson('/api/uploads')->json('upload_id');

    // 2 chunks om sequence-ordering te raken.
    $half = (int) ceil(strlen($bytes) / 2);
    test()->postJson("/api/uploads/{$uploadId}/chunk", [
        'sequence' => 0,
        'data' => base64_encode(substr($bytes, 0, $half)),
    ])->assertOk();

    $finalResponse = test()->postJson("/api/uploads/{$uploadId}/chunk", [
        'sequence' => 1,
        'data' => base64_encode(substr($bytes, $half)),
        'final' => true,
        'mime_type' => 'image/jpeg',
    ])->assertOk();

    return [(string) $finalResponse->json('upload_token'), $uploadId];
}

it('creates a post from a single media_token', function () {
    Storage::fake('public');
    $user = User::factory()->create();
    $circle = Circle::factory()->create(['user_id' => $user->id]);

    [$token, $sessionId] = uploadChunkedFakeImage($user);

    $this->actingAs($user)
        ->postJson('/api/posts', [
            'media_token' => $token,
            'circle_ids' => [$circle->id],
        ])
        ->assertCreated()
        ->assertJsonPath('data.media_type', 'image');

    expect(Post::count())->toBe(1)
        ->and(is_dir(UploadController::sessionDirectory($sessionId)))->toBeFalse();
});

it('creates a post from media_tokens[] with multiple items', function () {
    Storage::fake('public');
    $user = User::factory()->create();
    $circle = Circle::factory()->create(['user_id' => $user->id]);

    [$token1, $session1] = uploadChunkedFakeImage($user, 'a.jpg');
    [$token2, $session2] = uploadChunkedFakeImage($user, 'b.jpg');

    $this->actingAs($user)
        ->postJson('/api/posts', [
            'media_tokens' => [$token1, $token2],
            'circle_ids' => [$circle->id],
        ])
        ->assertCreated()
        ->assertJsonCount(2, 'data.media');

    expect(is_dir(UploadController::sessionDirectory($session1)))->toBeFalse()
        ->and(is_dir(UploadController::sessionDirectory($session2)))->toBeFalse();
});

it('rejects an unknown media_token', function () {
    Storage::fake('public');
    $user = User::factory()->create();
    $circle = Circle::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->postJson('/api/posts', [
            'media_token' => Str::uuid()->toString(),
            'circle_ids' => [$circle->id],
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['media']);
});

it('rejects a media_token from another user', function () {
    Storage::fake('public');
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $intruderCircle = Circle::factory()->create(['user_id' => $intruder->id]);

    [$token] = uploadChunkedFakeImage($owner);

    $this->actingAs($intruder)
        ->postJson('/api/posts', [
            'media_token' => $token,
            'circle_ids' => [$intruderCircle->id],
        ])
        ->assertStatus(422);
});

it('rejects mixing media_token with media_tokens', function () {
    Storage::fake('public');
    $user = User::factory()->create();
    $circle = Circle::factory()->create(['user_id' => $user->id]);

    [$token1] = uploadChunkedFakeImage($user, 'a.jpg');
    [$token2] = uploadChunkedFakeImage($user, 'b.jpg');

    $this->actingAs($user)
        ->postJson('/api/posts', [
            'media_token' => $token1,
            'media_tokens' => [$token2],
            'circle_ids' => [$circle->id],
        ])
        ->assertStatus(422);
});

// Video-bytes dispatchen TranscodeVideo wordt door
// PostControllerMultiMediaTest gedekt via UploadedFile::fake() — die fake
// stelt zelf de gerapporteerde mime type in zonder dat de body een echte
// MP4 hoeft te zijn. Onze chunked-upload-controller doet daarentegen een
// echte mime-detectie op het geassembleerde bestand, dus zonder een geldige
// MP4-byteseq is dat hier niet realistisch te testen.
