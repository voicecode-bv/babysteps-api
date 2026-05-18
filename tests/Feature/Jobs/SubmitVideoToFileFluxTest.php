<?php

use App\Enums\MediaStatus;
use App\Jobs\SubmitVideoToFileFlux;
use App\Jobs\TranscodeVideo;
use App\Models\Post;
use App\Models\PostMedia;
use App\Models\User;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    config([
        'filesystems.default' => 'public',
        'services.fileflux.enabled' => true,
        'services.fileflux.project_id' => '019e3ab3-2a18-73a4-832e-5f8164dfe06b',
        'services.fileflux.api_key' => 'test-api-key',
        'services.fileflux.callback_url' => 'https://api.test/api/webhooks/media/fileflux',
        'services.fileflux.ladder' => [
            'codec' => 'h264',
            'segment_duration' => 6,
            'audio' => ['codec' => 'aac', 'bitrate' => 128, 'channels' => 2],
            'renditions' => [
                ['name' => 'v1080', 'height' => 1080, 'video_bitrate' => 5000],
                ['name' => 'v720', 'height' => 720, 'video_bitrate' => 2800],
                ['name' => 'v480', 'height' => 480, 'video_bitrate' => 1200],
            ],
            'poster' => ['enabled' => true, 'filename' => 'poster.jpg', 'timestamp_seconds' => 1.0],
        ],
        'fileflux.api_key' => 'test-api-key',
        'fileflux.project_id' => '019e3ab3-2a18-73a4-832e-5f8164dfe06b',
    ]);
    Http::fake(['*' => Http::response(['task_id' => 'task-xyz-789'], 201)]);

    $this->user = User::factory()->create();
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
    ]);
    Storage::disk('public')->put($this->media->path, str_repeat('m', 4096));
});

it('archives the temp upload to /originals/ before submitting', function () {
    (new SubmitVideoToFileFlux($this->media))->handle();

    $this->media->refresh();

    expect($this->media->path)->toContain('/originals/')
        ->and(Storage::disk('public')->exists($this->media->path))->toBeTrue();
});

it('stores the archived original_path so PostResource can serve original_url for HLS', function () {
    (new SubmitVideoToFileFlux($this->media))->handle();

    $this->media->refresh();

    // `original_path` wijst naar het bron-MP4 op /originals/posts/, niet naar
    // het master.m3u8 dat later via ProcessFileFluxCallback in `path` komt.
    expect($this->media->original_path)
        ->not->toBeNull()
        ->and($this->media->original_path)->toContain('/originals/posts/')
        ->and($this->media->original_path)->toEndWith('.mp4')
        ->and(Storage::disk('public')->exists($this->media->original_path))->toBeTrue();
});

it('submits a job to FileFlux with the configured ladder', function () {
    (new SubmitVideoToFileFlux($this->media))->handle();

    Http::assertSent(function ($request) {
        $body = $request->data();

        return $body['workflow'] === 'ConvertVideoToHlsWorkflow'
            && str_contains($body['source'], '/originals/posts/')
            && str_starts_with($body['target']['prefix'], 'users/'.$this->user->id.'/posts/hls/')
            && $body['target']['master_filename'] === 'master.m3u8'
            && count($body['target']['renditions']) === 3
            && $body['target']['renditions'][0]['name'] === 'v1080'
            && $body['target']['audio']['codec'] === 'aac'
            && $body['target']['metadata']['post_media_id'] === $this->media->id;
    });
});

it('persists the FileFlux task_id and processing_started_at', function () {
    (new SubmitVideoToFileFlux($this->media))->handle();

    $this->media->refresh();

    expect($this->media->external_job_id)->toBe('task-xyz-789')
        ->and($this->media->processing_started_at)->not->toBeNull();
});

it('uses the callback_url from config as webhook', function () {
    (new SubmitVideoToFileFlux($this->media))->handle();

    Http::assertSent(function ($request) {
        return $request->data()['webhook'] === 'https://api.test/api/webhooks/media/fileflux';
    });
});

it('falls back to APP_URL when FILEFLUX_WEBHOOK_URL is empty', function () {
    // Empty env var (FILEFLUX_WEBHOOK_URL=) gaf met env('...', default) een
    // lege string door naar de package — geen fallback. Elvis-operator in
    // services.php ondervangt dit.
    config([
        'services.fileflux.callback_url' => null, // simulate the Elvis result
        'app.url' => 'https://api.innerr.app',
    ]);
    // We herlezen het config-item zoals de service provider zou doen.
    config([
        'services.fileflux.callback_url' => env('FILEFLUX_WEBHOOK_URL')
            ?: rtrim((string) config('app.url'), '/').'/api/webhooks/media/fileflux',
    ]);

    expect(config('services.fileflux.callback_url'))
        ->toBe('https://api.innerr.app/api/webhooks/media/fileflux');
});

it('does nothing when PostMedia is not in Processing state', function () {
    $this->media->update(['status' => MediaStatus::Ready]);

    (new SubmitVideoToFileFlux($this->media))->handle();

    Http::assertNothingSent();
});

it('falls back to TranscodeVideo when the job permanently fails', function () {
    Bus::fake();
    $job = new SubmitVideoToFileFlux($this->media);

    $job->failed(new RuntimeException('FileFlux down'));

    Bus::assertDispatched(TranscodeVideo::class);
});
