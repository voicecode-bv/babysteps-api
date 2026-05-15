<?php

use App\Services\BunnyCdn\UrlSigner;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->signer = new UrlSigner('https://media.innerr.app', 'test-token-key');
});

it('signs a path with HS256 prefix, base64url token and expires query param', function () {
    $expiresAt = Carbon::parse('2026-05-15 12:00:00', 'UTC');

    $url = $this->signer->sign('users/abc/posts/photo.jpg', $expiresAt);

    expect($url)->toStartWith('https://media.innerr.app/users/abc/posts/photo.jpg?token=HS256-');
    expect($url)->toContain('&expires='.$expiresAt->getTimestamp());

    // Token must be URL-safe base64: no +, /, or = characters.
    parse_str(parse_url($url, PHP_URL_QUERY) ?: '', $params);
    $token = preg_replace('/^HS256-/', '', $params['token']);
    expect($token)->toMatch('/^[A-Za-z0-9_-]+$/');
});

it('produces a token that matches an independently-computed HMAC-SHA256', function () {
    $expiresAt = Carbon::parse('2026-05-15 12:00:00', 'UTC');
    $expires = $expiresAt->getTimestamp();
    $path = '/users/abc/posts/photo.jpg';

    $expected = 'HS256-'.rtrim(strtr(base64_encode(
        hash_hmac('sha256', $path.$expires, 'test-token-key', true),
    ), '+/', '-_'), '=');

    $url = $this->signer->sign('users/abc/posts/photo.jpg', $expiresAt);
    parse_str(parse_url($url, PHP_URL_QUERY) ?: '', $params);

    expect($params['token'])->toBe($expected);
});

it('produces identical tokens for the same path and expiration (deterministic)', function () {
    $expiresAt = Carbon::parse('2026-05-15 12:00:00', 'UTC');

    expect($this->signer->sign('users/abc/x.jpg', $expiresAt))
        ->toBe($this->signer->sign('users/abc/x.jpg', $expiresAt));
});

it('produces different tokens for different paths', function () {
    $expiresAt = Carbon::parse('2026-05-15 12:00:00', 'UTC');

    expect($this->signer->sign('users/abc/x.jpg', $expiresAt))
        ->not->toBe($this->signer->sign('users/abc/y.jpg', $expiresAt));
});

it('produces different tokens when expiration changes', function () {
    $first = $this->signer->sign('users/abc/x.jpg', Carbon::parse('2026-05-15 12:00:00', 'UTC'));
    $second = $this->signer->sign('users/abc/x.jpg', Carbon::parse('2026-05-15 13:00:00', 'UTC'));

    expect($first)->not->toBe($second);
});

it('normalizes paths with or without a leading slash', function () {
    $expiresAt = Carbon::parse('2026-05-15 12:00:00', 'UTC');

    expect($this->signer->sign('/users/abc/x.jpg', $expiresAt))
        ->toBe($this->signer->sign('users/abc/x.jpg', $expiresAt));
});

it('strips trailing slash from configured base URL', function () {
    $signer = new UrlSigner('https://media.innerr.app/', 'key');

    $url = $signer->sign('a/b.jpg', Carbon::parse('2026-05-15 12:00:00', 'UTC'));

    expect($url)->toStartWith('https://media.innerr.app/a/b.jpg?');
    expect($url)->not->toStartWith('https://media.innerr.app//');
});

it('rejects non-positive expirations', function () {
    $this->signer->sign('a/b.jpg', Carbon::createFromTimestamp(0, 'UTC'));
})->throws(RuntimeException::class);

it('returns null from fromConfig when url or token key is missing', function () {
    config(['services.bunny_cdn.url' => null, 'services.bunny_cdn.token_key' => null]);
    expect(UrlSigner::fromConfig())->toBeNull();

    config(['services.bunny_cdn.url' => 'https://media.innerr.app', 'services.bunny_cdn.token_key' => null]);
    expect(UrlSigner::fromConfig())->toBeNull();

    config(['services.bunny_cdn.url' => null, 'services.bunny_cdn.token_key' => 'abc']);
    expect(UrlSigner::fromConfig())->toBeNull();
});

it('builds a signer from config when both values are set', function () {
    config([
        'services.bunny_cdn.url' => 'https://media.innerr.app',
        'services.bunny_cdn.token_key' => 'abc',
    ]);

    expect(UrlSigner::fromConfig())->toBeInstanceOf(UrlSigner::class);
});

it('signs a directory token with token_path included in the signature and URL', function () {
    $expiresAt = Carbon::parse('2026-05-15 12:00:00', 'UTC');

    $url = $this->signer->signDirectory(
        'users/abc/posts/uuid',
        'index.m3u8',
        $expiresAt,
    );

    expect($url)
        ->toStartWith('https://media.innerr.app/users/abc/posts/uuid/index.m3u8?')
        ->toContain('token=HS256-')
        ->toContain('expires='.$expiresAt->getTimestamp())
        ->toContain('token_path='.rawurlencode('/users/abc/posts/uuid/'));
});
