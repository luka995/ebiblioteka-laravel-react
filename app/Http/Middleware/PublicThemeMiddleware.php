<?php

namespace App\Http\Middleware;

use App\Support\PublicTheme;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PublicThemeMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $theme = strtolower((string) $request->query('theme'));

        if (in_array($theme, [PublicTheme::CLASSIC, PublicTheme::REFERENCE], true)) {
            cookie()->queue(cookie()->forever(PublicTheme::COOKIE, $theme));
        }

        return $next($request);
    }
}
