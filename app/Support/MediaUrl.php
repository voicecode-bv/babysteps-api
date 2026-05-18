<?php

namespace App\Support;

use App\Services\BunnyCdn\UrlSigner as BunnyCdnUrlSigner;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

class MediaUrl
{
    public static function disk(): Filesystem
    {
        return Storage::disk();
    }

    public static function sign(?string $path): ?string
    {
        if ($path === null) {
            return null;
        }

        if (preg_match('#/storage/(.+)$#', $path, $matches)) {
            $path = $matches[1];
        } elseif (preg_match('#^https?://#', $path)) {
            // External URL (e.g. picsum.photos seed data) — pass through unchanged.
            return $path;
        }

        $expires = static::expiry();

        // HLS-master playlist: één directory-token authoriseert master.m3u8 +
        // alle variant-playlists + segments onder dezelfde prefix. Eén signed
        // URL is genoeg om de hele stream af te spelen — anders zou elke
        // segment een eigen signed URL nodig hebben.
        if (str_ends_with($path, '.m3u8')) {
            return static::signHlsMaster($path, $expires);
        }

        $bunny = BunnyCdnUrlSigner::fromConfig();

        if ($bunny !== null) {
            return $bunny->sign($path, $expires);
        }

        $disk = static::disk();

        if (method_exists($disk, 'temporaryUrl')) {
            try {
                return $disk->temporaryUrl($path, $expires);
            } catch (\RuntimeException) {
                // Local disk doesn't support temporaryUrl, fall through
            }
        }

        return URL::signedRoute('api.media', ['path' => $path], $expires);
    }

    /**
     * Sign een HLS master playlist met een BunnyCDN directory-token. Voor
     * dev/test omgevingen zonder BunnyCDN valt het terug naar een per-file
     * signed route — voldoende voor unit tests, maar in productie wil je
     * altijd BunnyCDN met directory-tokens zodat segments delen.
     */
    protected static function signHlsMaster(string $path, \DateTimeInterface $expires): string
    {
        $bunny = BunnyCdnUrlSigner::fromConfig();

        if ($bunny !== null) {
            $directory = dirname($path).'/';
            $filename = basename($path);

            return $bunny->signDirectory($directory, $filename, $expires);
        }

        return URL::signedRoute('api.media', ['path' => $path], $expires);
    }

    /**
     * Map a display media path to the untouched original under the user's
     * `originals/` folder. Returns null when the input doesn't match a
     * user-scoped display path (e.g. legacy uploads or already-original paths).
     */
    public static function originalPath(?string $displayPath): ?string
    {
        if ($displayPath === null) {
            return null;
        }

        $originalPath = preg_replace(
            '#^(users/[0-9a-f-]{36})/(?!originals/)(.+)$#',
            '$1/originals/$2',
            $displayPath,
        );

        if ($originalPath === null || $originalPath === $displayPath) {
            return null;
        }

        return $originalPath;
    }

    /**
     * Stable expiry that snaps to the start of the next hour, so identical
     * paths produce identical signed URLs within a single hour window.
     * This lets browsers and the Bunny CDN edge cache by URL.
     */
    protected static function expiry(): \DateTimeInterface
    {
        return now()->startOfHour()->addHours(2);
    }
}
