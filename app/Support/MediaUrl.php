<?php

namespace App\Support;

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
        }

        $disk = static::disk();
        $expires = static::expiry();

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
     * This lets browsers and the Spaces CDN cache by URL.
     */
    protected static function expiry(): \DateTimeInterface
    {
        return now()->startOfHour()->addHours(2);
    }
}
