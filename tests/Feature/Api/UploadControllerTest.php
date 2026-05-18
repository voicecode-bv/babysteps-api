<?php

use App\Http\Controllers\Api\UploadController;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

afterEach(function () {
    File::deleteDirectory(UploadController::sessionsDirectory());
});

it('rejects upload-session calls without auth', function () {
    $this->postJson('/api/uploads')->assertStatus(401);
});

it('initialises a session tied to the user', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->postJson('/api/uploads')
        ->assertCreated()
        ->assertJsonStructure(['upload_id', 'chunk_size', 'max_chunks', 'max_total_bytes']);

    $uploadId = $response->json('upload_id');
    $directory = UploadController::sessionDirectory($uploadId);

    expect(is_dir($directory))->toBeTrue();
    $meta = json_decode((string) file_get_contents($directory.'/meta.json'), true);
    expect($meta['user_id'])->toBe($user->id);
});

it('rejects chunks from a different user', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();

    $uploadId = $this->actingAs($owner)
        ->postJson('/api/uploads')
        ->json('upload_id');

    $this->actingAs($intruder)
        ->postJson("/api/uploads/{$uploadId}/chunk", [
            'sequence' => 0,
            'data' => base64_encode('x'),
        ])
        ->assertStatus(404);
});

it('assembles chunks in correct order on finalize and returns upload_token', function () {
    $user = User::factory()->create();

    $uploadId = $this->actingAs($user)
        ->postJson('/api/uploads')
        ->json('upload_id');

    // Chunks out-of-order — finalize moet ze op sequence sorteren.
    $this->actingAs($user)
        ->postJson("/api/uploads/{$uploadId}/chunk", [
            'sequence' => 1,
            'data' => base64_encode('-world'),
        ])
        ->assertOk();

    $finalResponse = $this->actingAs($user)
        ->postJson("/api/uploads/{$uploadId}/chunk", [
            'sequence' => 0,
            'data' => base64_encode('hello'),
            'final' => true,
            'mime_type' => 'application/octet-stream',
        ])
        ->assertOk()
        ->assertJsonStructure(['upload_token', 'mime_type', 'size_bytes']);

    expect($finalResponse->json('upload_token'))->toBe($uploadId)
        ->and($finalResponse->json('size_bytes'))->toBe(11);

    $resolved = UploadController::consumeAssembled($uploadId, $user->id);
    expect($resolved)->not->toBeNull()
        ->and(file_get_contents($resolved['path']))->toBe('hello-world');
});

it('accepts multipart chunk uploads', function () {
    $user = User::factory()->create();

    $uploadId = $this->actingAs($user)
        ->postJson('/api/uploads')
        ->json('upload_id');

    $chunk = UploadedFile::fake()->createWithContent('chunk_0', 'binary-payload');

    $this->actingAs($user)
        ->post("/api/uploads/{$uploadId}/chunk", [
            'sequence' => 0,
            'final' => '1',
            'chunk' => $chunk,
        ], ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonStructure(['upload_token']);

    $resolved = UploadController::consumeAssembled($uploadId, $user->id);
    expect(file_get_contents($resolved['path']))->toBe('binary-payload');
});

it('aborts a session and cleans up chunks', function () {
    $user = User::factory()->create();

    $uploadId = $this->actingAs($user)
        ->postJson('/api/uploads')
        ->json('upload_id');

    $this->actingAs($user)
        ->postJson("/api/uploads/{$uploadId}/chunk", [
            'sequence' => 0,
            'data' => base64_encode('partial'),
        ])
        ->assertOk();

    $this->actingAs($user)
        ->deleteJson("/api/uploads/{$uploadId}")
        ->assertOk();

    expect(is_dir(UploadController::sessionDirectory($uploadId)))->toBeFalse();
});

it('rejects unknown session', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson('/api/uploads/'.Str::uuid().'/chunk', [
            'sequence' => 0,
            'data' => base64_encode('hello'),
        ])
        ->assertStatus(404);
});

it('does not surface assembled session for the wrong user', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();

    $uploadId = $this->actingAs($owner)
        ->postJson('/api/uploads')
        ->json('upload_id');

    $this->actingAs($owner)
        ->postJson("/api/uploads/{$uploadId}/chunk", [
            'sequence' => 0,
            'data' => base64_encode('hello'),
            'final' => true,
        ])
        ->assertOk();

    expect(UploadController::consumeAssembled($uploadId, $other->id))->toBeNull()
        ->and(UploadController::consumeAssembled($uploadId, $owner->id))->not->toBeNull();
});

it('GC command deletes stale sessions but keeps fresh ones', function () {
    $user = User::factory()->create();

    $freshId = $this->actingAs($user)->postJson('/api/uploads')->json('upload_id');
    $staleId = $this->actingAs($user)->postJson('/api/uploads')->json('upload_id');

    touch(UploadController::sessionDirectory($staleId).'/meta.json', now()->subDays(2)->getTimestamp());

    $this->artisan('uploads:gc-sessions')->assertSuccessful();

    expect(is_dir(UploadController::sessionDirectory($freshId)))->toBeTrue()
        ->and(is_dir(UploadController::sessionDirectory($staleId)))->toBeFalse();
});
