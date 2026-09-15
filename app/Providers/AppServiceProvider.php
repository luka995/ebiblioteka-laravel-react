<?php

namespace App\Providers;

use App\Services\Isbn\CategoryTranslator;
use App\Services\Isbn\GoogleBooksProvider;
use App\Services\Isbn\IsbnMetadataService;
use App\Services\Isbn\IsbnNormalizer;
use App\Services\Isbn\NbsCatalogProvider;
use App\Services\Isbn\OpenLibraryProvider;
use App\Services\Security\TurnstileService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(TurnstileService::class, function (): TurnstileService {
            return new TurnstileService((string) config('services.turnstile.secret'));
        });

        $this->app->singleton(IsbnMetadataService::class, function ($app): IsbnMetadataService {
            return new IsbnMetadataService(
                $app->make(IsbnNormalizer::class),
                [
                    $app->make(NbsCatalogProvider::class),
                    $app->make(OpenLibraryProvider::class),
                    $app->make(GoogleBooksProvider::class),
                ],
                $app->make(CategoryTranslator::class),
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('contact', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });
    }
}
