<?php

namespace App\Services\BunnyCdn;

use DateTimeInterface;
use RuntimeException;

/**
 * Signs URLs for the Bunny.net Pull Zone using Advanced Token Authentication
 * (HMAC-SHA256). See https://docs.bunny.net/cdn/security/token-authentication/advanced.
 *
 * The signing message is constructed as:
 *
 *     {signaturePath}{expires}{signingData}{userIp}
 *
 * where `signaturePath` is the path the token authorizes (either the URL path
 * for single-file tokens, or `tokenPath` for directory tokens), `signingData`
 * is alphabetically-sorted query params joined as `key=value` pairs separated
 * by `&` (excluding `token` and `expires`), and `userIp` is the optional IP.
 *
 * The token is then `HS256-` followed by the base64url-encoded HMAC.
 */
class UrlSigner
{
    protected string $baseUrl;

    public function __construct(
        string $baseUrl,
        protected string $tokenKey,
    ) {
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    public static function fromConfig(): ?self
    {
        $baseUrl = config('services.bunny_cdn.url');
        $tokenKey = config('services.bunny_cdn.token_key');

        if (! is_string($baseUrl) || $baseUrl === '' || ! is_string($tokenKey) || $tokenKey === '') {
            return null;
        }

        return new self($baseUrl, $tokenKey);
    }

    /**
     * Sign a storage path and return a fully qualified Bunny CDN URL.
     *
     * @param  string  $path  Storage-relative path, with or without leading slash (e.g. `users/abc/posts/x.jpg`).
     * @param  DateTimeInterface  $expiresAt  When the URL expires.
     */
    public function sign(string $path, DateTimeInterface $expiresAt): string
    {
        $signaturePath = '/'.ltrim($path, '/');
        $expires = $expiresAt->getTimestamp();

        if ($expires <= 0) {
            throw new RuntimeException('Expiration must be a positive Unix timestamp.');
        }

        $token = $this->generateToken($signaturePath, $expires);

        return $this->baseUrl.$signaturePath.'?token='.$token.'&expires='.$expires;
    }

    /**
     * Sign a directory prefix using a path-based (HLS-style) token. Any file
     * under `$directoryPath` is then accessible via the same token. The
     * returned URL includes the requested file path appended to the prefix.
     *
     * @param  string  $directoryPath  Directory prefix the token covers (e.g. `users/abc/posts/uuid/`).
     * @param  string  $filePath  Specific file under the directory to link to (e.g. `index.m3u8`).
     */
    public function signDirectory(string $directoryPath, string $filePath, DateTimeInterface $expiresAt): string
    {
        $tokenPath = '/'.ltrim(rtrim($directoryPath, '/'), '/').'/';
        $expires = $expiresAt->getTimestamp();

        if ($expires <= 0) {
            throw new RuntimeException('Expiration must be a positive Unix timestamp.');
        }

        // BunnyCDN-spec: signing data gebruikt RAW (unencoded) waarden, zelfs
        // wanneer de URL zelf URL-encoded varianten bevat. Eerder gebruikten we
        // de encoded waarde in beide — intern consistent, maar BunnyCDN's
        // verifier computeert met raw → 403 op alle segments.
        $signingData = $this->buildSigningData(['token_path' => $tokenPath]);

        $token = $this->generateToken($tokenPath, $expires, $signingData);

        $fileSuffix = ltrim($filePath, '/');

        return $this->baseUrl.$tokenPath.$fileSuffix
            .'?token='.$token
            .'&expires='.$expires
            .'&token_path='.rawurlencode($tokenPath);
    }

    /**
     * @param  array<string, string>  $params
     */
    protected function buildSigningData(array $params): string
    {
        if ($params === []) {
            return '';
        }

        ksort($params);
        $pairs = [];

        foreach ($params as $key => $value) {
            $pairs[] = $key.'='.$value;
        }

        return implode('&', $pairs);
    }

    protected function generateToken(string $signaturePath, int $expires, string $signingData = '', string $userIp = ''): string
    {
        $message = $signaturePath.$expires.$signingData.$userIp;
        $hmac = hash_hmac('sha256', $message, $this->tokenKey, true);

        return 'HS256-'.$this->base64UrlEncode($hmac);
    }

    protected function base64UrlEncode(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }
}
