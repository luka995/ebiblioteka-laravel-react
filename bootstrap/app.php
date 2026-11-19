<?php

use App\Http\Middleware\EnsureEmailIsVerified;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\SetSessionCookieDomain;
use App\Services\DatabaseExceptionService;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

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
            SetLocale::class,
        ]);

        $middleware->alias([
            'verified' => EnsureEmailIsVerified::class,
        ]);

        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Generic fallback for unhandled PostgreSQL data-integrity errors on
        // API requests. Domain-specific pre-checks keep their own messages.
        $exceptions->render(function (QueryException $exception, Request $request) {
            if (! $request->expectsJson() && ! $request->is('api/*')) {
                return null;
            }

            $databaseErrors = app(DatabaseExceptionService::class);

            $messageKey = match (true) {
                $databaseErrors->isForeignKeyViolation($exception) => 'validation.custom.related_records_exist',
                $databaseErrors->isUniqueViolation($exception) => 'validation.custom.duplicate_record',
                $databaseErrors->isCheckConstraintViolation($exception) => 'validation.custom.invalid_data',
                default => null,
            };

            if ($messageKey === null) {
                return null;
            }

            // 422 follows the existing API convention for domain conflicts;
            // 409 is reserved for state conflicts (file not ready...).
            return response()->json([
                'message' => __($messageKey),
            ], 422);
        });

        $exceptions->render(function (Throwable $exception, Request $request) {
            if (! $request->expectsJson() && ! $request->is('api/*')) {
                return null;
            }

            if (
                $exception instanceof ValidationException
                || $exception instanceof AuthenticationException
                || $exception instanceof HttpResponseException
            ) {
                return null;
            }

            if ($exception instanceof HttpExceptionInterface && $exception->getStatusCode() < 500) {
                return null;
            }

            return response()->json([
                'message' => __('errors.server_error'),
            ], 500);
        });
    })->create();
