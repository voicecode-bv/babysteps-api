<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    private const SUPPORTED = ['en', 'nl', 'fr'];

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->hasHeader('Accept-Language')) {
            $preferred = $request->getPreferredLanguage(self::SUPPORTED);

            if ($preferred && in_array($preferred, self::SUPPORTED, true)) {
                app()->setLocale($preferred);
            }
        }

        return $next($request);
    }
}
