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

it('computes the signature with the raw token_path value (BunnyCDN spec)', function () {
    // BunnyCDN's verifier joint query-params alphabetisch met raw values.
    // De URL bevat rawurlencode, maar het token moet op de raw value zijn
    // berekend — anders krijgt elke segment-request 403 ondanks geldige token.
    $expiresAt = Carbon::parse('2026-05-15 12:00:00', 'UTC');
    $tokenPath = '/users/abc/posts/uuid/';
    $expires = $expiresAt->getTimestamp();

    // Forward-compute zoals BunnyCDN het zou doen: HMAC over
    //   tokenPath + expires + 'token_path=' + rawTokenPath + '' (no userIp)
    $message = $tokenPath.$expires.'token_path='.$tokenPath;
    $expectedHmac = hash_hmac('sha256', $message, 'test-token-key', true);
    $expectedToken = 'HS256-'.rtrim(strtr(base64_encode($expectedHmac), '+/', '-_'), '=');

    $url = $this->signer->signDirectory(
        'users/abc/posts/uuid',
        'index.m3u8',
        $expiresAt,
    );

    expect($url)->toContain('token='.$expectedToken);
});

it('signs segments in a flat HLS layout (pbmedia/laravel-ffmpeg default)', function () {
    // pbmedia/laravel-ffmpeg produceert flat output: master.m3u8, master_2.m3u8
    // én master_2_1200_00000.ts alle in dezelfde directory. We moeten elke
    // segment-request signen tegen het directory-token-pad van de hele HLS map.
    $expiresAt = Carbon::parse('2026-05-15 12:00:00', 'UTC');

    $url = $this->signer->signDirectory(
        'users/abc/posts/hls/uuid',
        'master_2_1200_00000.ts',
        $expiresAt,
    );

    expect($url)
        ->toStartWith('https://media.innerr.app/users/abc/posts/hls/uuid/master_2_1200_00000.ts?')
        ->toContain('token=HS256-')
        ->toContain('token_path='.rawurlencode('/users/abc/posts/hls/uuid/'));
});
