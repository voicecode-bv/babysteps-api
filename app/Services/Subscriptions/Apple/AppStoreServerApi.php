<?php

namespace App\Services\Subscriptions\Apple;

use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class AppStoreServerApi
{
    public function __construct(
        private HttpFactory $http,
        private AppStoreServerJwt $jwt,
        private string $environment,
        /** @var array<string, string> */
        private array $baseUrls,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function getAllSubscriptionStatuses(string $originalTransactionId, ?string $preferredEnvironment = null): array
    {
        return $this->get("/inApps/v1/subscriptions/{$originalTransactionId}", $preferredEnvironment);
    }

    /**
     * @return array<string, mixed>
     */
    public function getTransactionInfo(string $transactionId, ?string $preferredEnvironment = null): array
    {
        return $this->get("/inApps/v1/transactions/{$transactionId}", $preferredEnvironment);
    }

    /**
     * Asks Apple to send a TEST notification to the configured server URL.
     *
     * @return array<string, mixed>
     */
    public function requestTestNotification(): array
    {
        return $this->post('/inApps/v1/notifications/test');
    }

    /**
     * Looks up the delivery status of a previously requested test notification.
     *
     * @return array<string, mixed>
     */
    public function getTestNotificationStatus(string $testNotificationToken): array
    {
        return $this->get("/inApps/v1/notifications/test/{$testNotificationToken}");
    }

    /**
     * @return array<string, mixed>
     */
    private function get(string $path, ?string $preferredEnvironment = null): array
    {
        $primary = $this->normalizeEnvironment($preferredEnvironment) ?? $this->environment;
        $response = $this->doGet($path, $primary);

        // Apple returns errorCode 4040010 ("Transaction id not found") when the
        // transaction lives in the other environment (TestFlight/sandbox hitting
        // production, or vice versa). Apple's docs prescribe a retry on the
        // opposite environment in that case.
        if ($this->isTransactionNotInEnvironment($response)) {
            $fallback = $this->oppositeEnvironment($primary);

            if ($fallback !== null) {
                Log::channel('subscriptions')->info('apple authoritative fetch: env fallback', [
                    'path' => $path,
                    'from' => $primary,
                    'to' => $fallback,
                ]);

                $retry = $this->doGet($path, $fallback);

                if ($retry->successful()) {
                    return (array) $retry->json();
                }

                $response = $retry;
            }
        }

        $this->ensureOk($response, $path);

        return (array) $response->json();
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    private function post(string $path, array $body = []): array
    {
        $response = $this->http->withToken($this->jwt->bearerToken())
            ->acceptJson()
            ->timeout(15)
            ->post($this->base($this->environment).$path, $body);

        $this->ensureOk($response, $path);

        return (array) $response->json();
    }

    private function doGet(string $path, string $environment): Response
    {
        return $this->http->withToken($this->jwt->bearerToken())
            ->acceptJson()
            ->timeout(15)
            ->get($this->base($environment).$path);
    }

    private function isTransactionNotInEnvironment(Response $response): bool
    {
        if ($response->status() !== 404) {
            return false;
        }

        return (int) ($response->json('errorCode') ?? 0) === 4040010;
    }

    private function oppositeEnvironment(string $environment): ?string
    {
        return match ($environment) {
            'production' => 'sandbox',
            'sandbox' => 'production',
            default => null,
        };
    }

    private function normalizeEnvironment(?string $environment): ?string
    {
        if ($environment === null || $environment === '') {
            return null;
        }

        $lower = strtolower($environment);

        return in_array($lower, ['production', 'sandbox'], true) ? $lower : null;
    }

    private function base(string $environment): string
    {
        $base = $this->baseUrls[$environment] ?? null;

        if ($base === null) {
            throw new RuntimeException("Unknown Apple IAP environment [{$environment}].");
        }

        return $base;
    }

    private function ensureOk(Response $response, string $path): void
    {
        if ($response->successful()) {
            return;
        }

        throw new RuntimeException(sprintf(
            'Apple App Store Server API request to %s failed with status %d: %s',
            $path,
            $response->status(),
            (string) $response->body(),
        ));
    }
}
