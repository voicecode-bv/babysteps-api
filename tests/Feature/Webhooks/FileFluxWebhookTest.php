<?php

use App\Enums\MediaStatus;
use App\Jobs\ProcessFileFluxCallback;
use App\Models\Circle;
use App\Models\Post;
use App\Models\PostMedia;
use App\Models\User;
use Illuminate\Support\Facades\Bus;

beforeEach(function () {
    config(['services.fileflux.webhook_secret' => 'test-secret']);

    $this->user = User::factory()->create();
    $this->circle = Circle::factory()->create(['user_id' => $this->user->id]);
    $this->post = Post::factory()->create([
        'user_id' => $this->user->id,
        'media_status' => MediaStatus::Processing,
    ]);
    $this->media = PostMedia::create([
        'post_id' => $this->post->id,
        'sort_order' => 0,
        'path' => 'users/'.$this->user->id.'/posts/temp.mp4',
        'type' => 'video',
        'format' => 'mp4',
        'status' => MediaStatus::Processing,
        'external_job_id' => 'task-abc-123',
    ]);

    Bus::fake();
});

function signedBody(array $payload, string $secret): array
{
    $raw = json_encode($payload);

    return [
        'body' => $raw,
        'signature' => hash_hmac('sha256', $raw, $secret),
    ];
}

it('rejects a webhook with an invalid signature', function () {
    $signed = signedBody(['task_id' => 'task-abc-123', 'status' => 'completed'], 'wrong-secret');

    $this->call(
        'POST',
        '/api/webhooks/media/fileflux',
        [],
        [],
        [],
        ['HTTP_X-FileFlux-Signature' => $signed['signature'], 'CONTENT_TYPE' => 'application/json'],
        $signed['body'],
    )->assertStatus(401);

    Bus::assertNotDispatched(ProcessFileFluxCallback::class);
});

it('returns 422 when task_id or status is missing', function () {
    $signed = signedBody(['status' => 'completed'], 'test-secret');

    $this->call(
        'POST',
        '/api/webhooks/media/fileflux',
        [],
        [],
        [],
        ['HTTP_X-FileFlux-Signature' => $signed['signature'], 'CONTENT_TYPE' => 'application/json'],
        $signed['body'],
    )->assertStatus(422);
});

it('accepts a properly signed completion and dispatches the callback job', function () {
    $payload = [
        'task_id' => 'task-abc-123',
        'status' => 'completed',
        'outputs' => [
            'master' => 'users/'.$this->user->id.'/posts/hls/'.$this->media->id.'/master.m3u8',
            'renditions' => ['v1080/playlist.m3u8', 'v720/playlist.m3u8', 'v480/playlist.m3u8'],
            'poster' => 'users/'.$this->user->id.'/posts/hls/'.$this->media->id.'/poster.jpg',
        ],
    ];
    $signed = signedBody($payload, 'test-secret');

    $this->call(
        'POST',
        '/api/webhooks/media/fileflux',
        [],
        [],
        [],
        ['HTTP_X-FileFlux-Signature' => $signed['signature'], 'CONTENT_TYPE' => 'application/json'],
        $signed['body'],
    )->assertStatus(202);

    Bus::assertDispatched(ProcessFileFluxCallback::class, function (ProcessFileFluxCallback $job) use ($payload) {
        return $job->taskId === 'task-abc-123'
            && $job->status === 'completed'
            && $job->outputs === $payload['outputs'];
    });
});

it('is idempotent: a second webhook for an already-Ready media does not redispatch', function () {
    $this->media->update(['status' => MediaStatus::Ready]);

    $payload = ['task_id' => 'task-abc-123', 'status' => 'completed', 'outputs' => []];
    $signed = signedBody($payload, 'test-secret');

    $this->call(
        'POST',
        '/api/webhooks/media/fileflux',
        [],
        [],
        [],
        ['HTTP_X-FileFlux-Signature' => $signed['signature'], 'CONTENT_TYPE' => 'application/json'],
        $signed['body'],
    )->assertStatus(200);

    Bus::assertNotDispatched(ProcessFileFluxCallback::class);
});

it('returns 503 when webhook secret is not configured', function () {
    config(['services.fileflux.webhook_secret' => null]);

    $payload = ['task_id' => 'task-abc-123', 'status' => 'completed'];
    $signed = signedBody($payload, 'test-secret');

    $this->call(
        'POST',
        '/api/webhooks/media/fileflux',
        [],
        [],
        [],
        ['HTTP_X-FileFlux-Signature' => $signed['signature'], 'CONTENT_TYPE' => 'application/json'],
        $signed['body'],
    )->assertStatus(503);
});

it('dispatches with failed status payload too', function () {
    $payload = [
        'task_id' => 'task-abc-123',
        'status' => 'failed',
        'outputs' => [],
        'error' => ['code' => 'TRANSCODE_FAILED', 'message' => 'codec unsupported'],
    ];
    $signed = signedBody($payload, 'test-secret');

    $this->call(
        'POST',
        '/api/webhooks/media/fileflux',
        [],
        [],
        [],
        ['HTTP_X-FileFlux-Signature' => $signed['signature'], 'CONTENT_TYPE' => 'application/json'],
        $signed['body'],
    )->assertStatus(202);

    Bus::assertDispatched(ProcessFileFluxCallback::class, function (ProcessFileFluxCallback $job) {
        return $job->status === 'failed'
            && $job->errorMessage === 'codec unsupported';
    });
});
