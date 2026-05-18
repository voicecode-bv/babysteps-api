<?php

use App\Enums\MediaStatus;
use App\Jobs\TranscodeVideo;
use App\Models\Post;
use App\Models\PostMedia;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;

beforeEach(function () {
    config(['services.fileflux.job_timeout_minutes' => 30]);
    Bus::fake();

    $this->user = User::factory()->create();
    $this->post = Post::factory()->create(['user_id' => $this->user->id]);
});

function makeStuckMedia(string $userId, string $postId, ?Carbon $startedAt): PostMedia
{
    return PostMedia::create([
        'post_id' => $postId,
        'sort_order' => 0,
        'path' => "users/{$userId}/originals/posts/video.mp4",
        'type' => 'video',
        'format' => 'mp4',
        'status' => MediaStatus::Processing,
        'external_job_id' => 'task-'.uniqid(),
        'processing_started_at' => $startedAt,
    ]);
}

it('reconciles stuck jobs past the timeout and dispatches local fallback', function () {
    Carbon::setTestNow('2026-05-18 12:00:00');
    $stuck = makeStuckMedia($this->user->id, $this->post->id, now()->subHour());

    $this->artisan('media:reconcile-fileflux-jobs')->assertSuccessful();

    $stuck->refresh();
    expect($stuck->external_job_id)->toBeNull()
        ->and($stuck->processing_started_at)->toBeNull();

    Bus::assertDispatched(TranscodeVideo::class, function (TranscodeVideo $job) use ($stuck) {
        return $job->postMedia->id === $stuck->id;
    });
});

it('leaves jobs younger than the timeout alone', function () {
    Carbon::setTestNow('2026-05-18 12:00:00');
    $fresh = makeStuckMedia($this->user->id, $this->post->id, now()->subMinutes(5));

    $this->artisan('media:reconcile-fileflux-jobs')->assertSuccessful();

    $fresh->refresh();
    expect($fresh->external_job_id)->not->toBeNull();
    Bus::assertNotDispatched(TranscodeVideo::class);
});

it('skips media that is already Ready', function () {
    Carbon::setTestNow('2026-05-18 12:00:00');
    $done = PostMedia::create([
        'post_id' => $this->post->id,
        'sort_order' => 0,
        'path' => "users/{$this->user->id}/posts/hls/x/master.m3u8",
        'type' => 'video',
        'format' => 'hls',
        'status' => MediaStatus::Ready,
        'external_job_id' => 'task-done',
        'processing_started_at' => now()->subHours(2),
    ]);

    $this->artisan('media:reconcile-fileflux-jobs')->assertSuccessful();

    Bus::assertNotDispatched(TranscodeVideo::class);
    expect($done->fresh()->external_job_id)->toBe('task-done');
});

it('skips media without an external_job_id', function () {
    Carbon::setTestNow('2026-05-18 12:00:00');
    PostMedia::create([
        'post_id' => $this->post->id,
        'sort_order' => 0,
        'path' => "users/{$this->user->id}/originals/posts/video.mp4",
        'type' => 'video',
        'format' => 'mp4',
        'status' => MediaStatus::Processing,
        'external_job_id' => null,
        'processing_started_at' => now()->subHours(2),
    ]);

    $this->artisan('media:reconcile-fileflux-jobs')->assertSuccessful();

    Bus::assertNotDispatched(TranscodeVideo::class);
});
