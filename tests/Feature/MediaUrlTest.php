<?php

use App\Support\MediaUrl;
use Illuminate\Support\Carbon;

it('returns null for null path', function () {
    expect(MediaUrl::sign(null))->toBeNull();
});

it('produces identical signed urls within the same hour window', function () {
    Carbon::setTestNow('2026-04-27 10:15:00');
    $first = MediaUrl::sign('avatars/abc.jpg');

    Carbon::setTestNow('2026-04-27 10:45:30');
    $second = MediaUrl::sign('avatars/abc.jpg');

    expect($first)->toBe($second);
});

it('produces a different signed url after the hour window rolls over', function () {
    Carbon::setTestNow('2026-04-27 10:15:00');
    $first = MediaUrl::sign('avatars/abc.jpg');

    Carbon::setTestNow('2026-04-27 11:05:00');
    $second = MediaUrl::sign('avatars/abc.jpg');

    expect($first)->not->toBe($second);
});

it('strips a leading /storage/ prefix from the path', function () {
    Carbon::setTestNow('2026-04-27 10:15:00');

    $withPrefix = MediaUrl::sign('https://example.test/storage/avatars/abc.jpg');
    $withoutPrefix = MediaUrl::sign('avatars/abc.jpg');

    expect($withPrefix)->toBe($withoutPrefix);
});

it('routes through the Bunny CDN signer when configured', function () {
    Carbon::setTestNow('2026-04-27 10:15:00');

    config([
        'services.bunny_cdn.url' => 'https://media.innerr.app',
        'services.bunny_cdn.token_key' => 'test-token-key',
    ]);

    $url = MediaUrl::sign('users/abc/posts/photo.jpg');

    expect($url)
        ->toStartWith('https://media.innerr.app/users/abc/posts/photo.jpg?')
        ->toContain('token=HS256-')
        ->toContain('expires=');
});

it('strips the /storage/ prefix before signing with Bunny CDN', function () {
    Carbon::setTestNow('2026-04-27 10:15:00');

    config([
        'services.bunny_cdn.url' => 'https://media.innerr.app',
        'services.bunny_cdn.token_key' => 'test-token-key',
    ]);

    $direct = MediaUrl::sign('avatars/abc.jpg');
    $withPrefix = MediaUrl::sign('https://example.test/storage/avatars/abc.jpg');

    expect($withPrefix)->toBe($direct);
});

it('passes through external URLs unchanged (e.g. picsum seed data)', function () {
    config([
        'services.bunny_cdn.url' => 'https://media-dev.innerr.app',
        'services.bunny_cdn.token_key' => 'test-token-key',
    ]);

    $external = 'https://picsum.photos/seed/6296/600/600';

    expect(MediaUrl::sign($external))->toBe($external);
});

it('falls back to a signed Laravel route when Bunny is not configured', function () {
    Carbon::setTestNow('2026-04-27 10:15:00');

    config([
        'services.bunny_cdn.url' => null,
        'services.bunny_cdn.token_key' => null,
    ]);

    $url = MediaUrl::sign('avatars/abc.jpg');

    expect($url)->toContain('/api/media/');
    expect($url)->toContain('signature=');
});

it('signs an HLS master playlist with a Bunny directory token', function () {
    Carbon::setTestNow('2026-04-27 10:15:00');

    config([
        'services.bunny_cdn.url' => 'https://media.innerr.app',
        'services.bunny_cdn.token_key' => 'test-token-key',
    ]);

    $url = MediaUrl::sign('users/abc/posts/hls/m1/master.m3u8');

    // Directory-token signatures carry token_path zodat segments + variant
    // playlists onder dezelfde prefix dezelfde token kunnen gebruiken.
    expect($url)
        ->toStartWith('https://media.innerr.app/users/abc/posts/hls/m1/master.m3u8?')
        ->toContain('token=HS256-')
        ->toContain('token_path=')
        ->toContain('expires=');
});

it('falls back to a signed Laravel route for HLS when Bunny is not configured', function () {
    Carbon::setTestNow('2026-04-27 10:15:00');

    config([
        'services.bunny_cdn.url' => null,
        'services.bunny_cdn.token_key' => null,
    ]);

    $url = MediaUrl::sign('users/abc/posts/hls/m1/master.m3u8');

    expect($url)
        ->toContain('/api/media/')
        ->toContain('master.m3u8')
        ->toContain('signature=');
});
