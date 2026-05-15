<?php

use App\Services\Subscriptions\Apple\AppStoreServerApi;
use App\Services\Subscriptions\Apple\AppStoreServerJwt;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->jwt = Mockery::mock(AppStoreServerJwt::class);
    $this->jwt->shouldReceive('bearerToken')->andReturn('fake-jwt');

    $this->baseUrls = [
        'production' => 'https://api.storekit.itunes.apple.com',
        'sandbox' => 'https://api.storekit-sandbox.itunes.apple.com',
    ];
});

function makeApi(object $jwt, array $baseUrls, string $environment): AppStoreServerApi
{
    return new AppStoreServerApi(
        http: app(HttpFactory::class),
        jwt: $jwt,
        environment: $environment,
        baseUrls: $baseUrls,
    );
}

it('falls back to sandbox when production returns 4040010', function () {
    Http::fake([
        'api.storekit.itunes.apple.com/*' => Http::response([
            'errorCode' => 4040010,
            'errorMessage' => 'Transaction id not found.',
        ], 404),
        'api.storekit-sandbox.itunes.apple.com/*' => Http::response([
            'environment' => 'Sandbox',
            'data' => [['status' => 1]],
        ], 200),
    ]);

    $api = makeApi($this->jwt, $this->baseUrls, 'production');

    $result = $api->getAllSubscriptionStatuses('apple-orig-99');

    expect($result['environment'])->toBe('Sandbox')
        ->and($result['data'][0]['status'])->toBe(1);

    Http::assertSentInOrder([
        fn ($r) => str_starts_with($r->url(), 'https://api.storekit.itunes.apple.com/'),
        fn ($r) => str_starts_with($r->url(), 'https://api.storekit-sandbox.itunes.apple.com/'),
    ]);
});

it('falls back to production when sandbox returns 4040010', function () {
    Http::fake([
        'api.storekit-sandbox.itunes.apple.com/*' => Http::response([
            'errorCode' => 4040010,
            'errorMessage' => 'Transaction id not found.',
        ], 404),
        'api.storekit.itunes.apple.com/*' => Http::response([
            'environment' => 'Production',
            'data' => [['status' => 1]],
        ], 200),
    ]);

    $api = makeApi($this->jwt, $this->baseUrls, 'sandbox');

    $result = $api->getAllSubscriptionStatuses('apple-orig-42');

    expect($result['environment'])->toBe('Production');
});

it('does not retry on 404 with a different errorCode', function () {
    Http::fake([
        'api.storekit.itunes.apple.com/*' => Http::response([
            'errorCode' => 4040005,
            'errorMessage' => 'Some other 404.',
        ], 404),
    ]);

    $api = makeApi($this->jwt, $this->baseUrls, 'production');

    expect(fn () => $api->getAllSubscriptionStatuses('apple-orig-77'))
        ->toThrow(RuntimeException::class, 'failed with status 404');

    Http::assertSentCount(1);
});

it('honors the preferred environment hint and skips the wasted production roundtrip', function () {
    Http::fake([
        'api.storekit-sandbox.itunes.apple.com/*' => Http::response([
            'environment' => 'Sandbox',
            'data' => [['status' => 1]],
        ], 200),
    ]);

    // Configured environment is production, but we hint that this transaction
    // is in sandbox — the request should go straight to sandbox, no fallback.
    $api = makeApi($this->jwt, $this->baseUrls, 'production');

    $result = $api->getAllSubscriptionStatuses('apple-orig-hint', preferredEnvironment: 'sandbox');

    expect($result['environment'])->toBe('Sandbox');

    Http::assertSentCount(1);
    Http::assertSent(fn ($r) => str_starts_with($r->url(), 'https://api.storekit-sandbox.itunes.apple.com/'));
});

it('still falls back from the hinted environment if the transaction is not there', function () {
    Http::fake([
        'api.storekit-sandbox.itunes.apple.com/*' => Http::response([
            'errorCode' => 4040010,
            'errorMessage' => 'Transaction id not found.',
        ], 404),
        'api.storekit.itunes.apple.com/*' => Http::response([
            'environment' => 'Production',
            'data' => [['status' => 1]],
        ], 200),
    ]);

    $api = makeApi($this->jwt, $this->baseUrls, 'production');

    // Hint says sandbox but the transaction is actually in production — must
    // still fall back so we don't lose the request.
    $result = $api->getAllSubscriptionStatuses('apple-orig-stale-hint', preferredEnvironment: 'sandbox');

    expect($result['environment'])->toBe('Production');
});

it('ignores unrecognized environment hints and falls back to the configured environment', function () {
    Http::fake([
        'api.storekit.itunes.apple.com/*' => Http::response([
            'environment' => 'Production',
            'data' => [['status' => 1]],
        ], 200),
    ]);

    $api = makeApi($this->jwt, $this->baseUrls, 'production');

    $api->getAllSubscriptionStatuses('apple-orig-garbage-hint', preferredEnvironment: 'xcode');

    Http::assertSentCount(1);
    Http::assertSent(fn ($r) => str_starts_with($r->url(), 'https://api.storekit.itunes.apple.com/'));
});

it('reports the retry response error when both environments return 4040010', function () {
    Http::fake([
        '*' => Http::response([
            'errorCode' => 4040010,
            'errorMessage' => 'Transaction id not found.',
        ], 404),
    ]);

    $api = makeApi($this->jwt, $this->baseUrls, 'production');

    expect(fn () => $api->getAllSubscriptionStatuses('apple-orig-00'))
        ->toThrow(RuntimeException::class, 'failed with status 404');

    Http::assertSentCount(2);
});
