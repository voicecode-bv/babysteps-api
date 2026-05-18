<?php

use App\Enums\MediaStatus;
use App\Jobs\ProcessFileFluxCallback;
use App\Jobs\TranscodeVideo;
use App\Models\Post;
use App\Models\PostMedia;
use App\Models\User;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    config(['filesystems.default' => 'public']);
    Bus::fake();

    $this->user = User::factory()->create(['storage_used_bytes' => 0]);
    $this->post = Post::factory()->create([
        'user_id' => $this->user->id,
        'media_status' => MediaStatus::Processing,
    ]);
    $this->media = PostMedia::create([
        'post_id' => $this->post->id,
        'sort_order' => 0,
        'path' => 'users/'.$this->user->id.'/originals/posts/source.mp4',
        'type' => 'video',
        'format' => 'mp4',
        'status' => MediaStatus::Processing,
        'external_job_id' => 'task-abc',
        'processing_started_at' => now(),
    ]);
});

function seedHlsOutputs(string $masterPath): void
{
    $directory = dirname($masterPath);

    Storage::disk('public')->put($masterPath, "#EXTM3U\n");
    Storage::disk('public')->put($directory.'/v1080/playlist.m3u8', "#EXTM3U\nseg-0001.m4s\n");
    Storage::disk('public')->put($directory.'/v1080/seg-0001.m4s', str_repeat('x', 1024));
    Storage::disk('public')->put($directory.'/v720/playlist.m3u8', "#EXTM3U\n");
    Storage::disk('public')->put($directory.'/v720/seg-0001.m4s', str_repeat('y', 512));
    Storage::disk('public')->put($directory.'/poster.jpg', str_repeat('z', 256));
}

it('updates PostMedia to Ready with master path and poster on successful callback', function () {
    $masterPath = 'users/'.$this->user->id.'/posts/hls/'.$this->media->id.'/master.m3u8';
    seedHlsOutputs($masterPath);

    (new ProcessFileFluxCallback(
        taskId: 'task-abc',
        status: 'completed',
        outputs: [
            'master' => $masterPath,
            'poster' => dirname($masterPath).'/poster.jpg',
        ],
    ))->handle();

    $this->media->refresh();

    expect($this->media->status)->toBe(MediaStatus::Ready)
        ->and($this->media->path)->toBe($masterPath)
        ->and($this->media->format)->toBe('hls')
        ->and($this->media->thumbnail_path)->toBe(dirname($masterPath).'/poster.jpg');
});

it('tracks storage for each new HLS file', function () {
    $masterPath = 'users/'.$this->user->id.'/posts/hls/'.$this->media->id.'/master.m3u8';
    seedHlsOutputs($masterPath);

    (new ProcessFileFluxCallback(
        taskId: 'task-abc',
        status: 'completed',
        outputs: ['master' => $masterPath],
    ))->handle();

    $this->user->refresh();
    // master "#EXTM3U\n" = 8 bytes + v1080 playlist 24 + seg 1024 + v720 playlist 8 + seg 512 + poster 256
    expect($this->user->storage_used_bytes)->toBeGreaterThan(1024);
});

it('deletes the old temp MP4 outside /originals/ after success', function () {
    $tempPath = 'users/'.$this->user->id.'/posts/temp.mp4';
    Storage::disk('public')->put($tempPath, str_repeat('o', 2048));
    $this->media->update(['path' => $tempPath]);

    $masterPath = 'users/'.$this->user->id.'/posts/hls/'.$this->media->id.'/master.m3u8';
    seedHlsOutputs($masterPath);

    (new ProcessFileFluxCallback(
        taskId: 'task-abc',
        status: 'completed',
        outputs: ['master' => $masterPath],
    ))->handle();

    expect(Storage::disk('public')->exists($tempPath))->toBeFalse();
});

it('preserves the file under /originals/', function () {
    // media->path already points to /originals/, should NOT be deleted.
    $originalPath = $this->media->path;
    Storage::disk('public')->put($originalPath, str_repeat('o', 100));

    $masterPath = 'users/'.$this->user->id.'/posts/hls/'.$this->media->id.'/master.m3u8';
    seedHlsOutputs($masterPath);

    (new ProcessFileFluxCallback(
        taskId: 'task-abc',
        status: 'completed',
        outputs: ['master' => $masterPath],
    ))->handle();

    expect(Storage::disk('public')->exists($originalPath))->toBeTrue();
});

it('is idempotent: a second call on already-Ready media does nothing', function () {
    $masterPath = 'users/'.$this->user->id.'/posts/hls/'.$this->media->id.'/master.m3u8';
    seedHlsOutputs($masterPath);
    $this->media->update([
        'status' => MediaStatus::Ready,
        'path' => $masterPath,
    ]);

    $userBytesBefore = $this->user->fresh()->storage_used_bytes;

    (new ProcessFileFluxCallback(
        taskId: 'task-abc',
        status: 'completed',
        outputs: ['master' => $masterPath],
    ))->handle();

    expect($this->user->fresh()->storage_used_bytes)->toBe($userBytesBefore);
});

it('dispatches TranscodeVideo fallback on failed status', function () {
    (new ProcessFileFluxCallback(
        taskId: 'task-abc',
        status: 'failed',
        outputs: [],
        errorMessage: 'codec unsupported',
    ))->handle();

    Bus::assertDispatched(TranscodeVideo::class);

    $this->media->refresh();
    expect($this->media->external_job_id)->toBeNull();
});

it('throws when master file is not yet on disk (retry case)', function () {
    (new ProcessFileFluxCallback(
        taskId: 'task-abc',
        status: 'completed',
        outputs: ['master' => 'users/x/posts/hls/m/master.m3u8'],
    ))->handle();
})->throws(RuntimeException::class, 'Master playlist not on disk');

it('does nothing when no PostMedia matches the task_id', function () {
    $this->media->update(['external_job_id' => 'other-task']);

    (new ProcessFileFluxCallback(
        taskId: 'task-abc',
        status: 'completed',
        outputs: [],
    ))->handle();

    $this->media->refresh();
    expect($this->media->status)->toBe(MediaStatus::Processing);
});
