<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public const LOCALES = ['sr-Cyrl', 'sr-Latn', 'en'];

    public const DEFAULT = 'sr-Cyrl';

    public function handle(Request $request, Closure $next): Response
    {
        app()->setLocale($this->resolve($request));

        return $next($request);
    }

    private function resolve(Request $request): string
    {
        $header = strtolower(trim((string) $request->header('X-Locale')));

        if (in_array($header, ['sr-cyrl', 'sr_cyrl', 'sr'], true)) {
            return 'sr-Cyrl';
        }

        if (in_array($header, ['sr-latn', 'sr_latn'], true)) {
            return 'sr-Latn';
        }

        if (in_array($header, ['en', 'en-us', 'en_gb', 'en-gb'], true)) {
            return 'en';
        }

        return self::DEFAULT;
    }
}
