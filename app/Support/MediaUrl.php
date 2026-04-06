<?php

namespace App\Support;

use Illuminate\Support\Facades\URL;

class MediaUrl
{
    public static function sign(?string $path): ?string
    {
        if ($path === null) {
            return null;
        }

        return URL::signedRoute('api.media', ['path' => $path], now()->addMinutes(60));
    }
}
