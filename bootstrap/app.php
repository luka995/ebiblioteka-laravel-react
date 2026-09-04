<?php

use App\Http\Middleware\EnsureEmailIsVerified;
use App\Http\Middleware\PublicThemeMiddleware;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\SetSessionCookieDomain;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');

        $middleware->api(prepend: [
            SetSessionCookieDomain::class,
            EnsureFrontendRequestsAreStateful::class,
            SetLocale::class,
        ]);

        $middleware->web(prepend: [
            SetSessionCookieDomain::class,
            PublicThemeMiddleware::class,
        ]);

        $middleware->alias([
            'verified' => EnsureEmailIsVerified::class,
        ]);

        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
