<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetSessionCookieDomain
{
    public function handle(Request $request, Closure $next): Response
    {
        $domain = $this->resolveCookieDomain($request);

        if ($domain !== null) {
            config(['session.domain' => $domain]);
        }

        return $next($request);
    }

    private function resolveCookieDomain(Request $request): ?string
    {
        $suffix = config('session.cookie_domain_public');

        if (! is_string($suffix) || $suffix === '') {
            return null;
        }

        $suffix = ltrim($suffix, '.');

        $host = strtolower($request->getHost());

        if ($host === $suffix || str_ends_with($host, '.'.$suffix)) {
            return '.'.$suffix;
        }

        return null;
    }
}
