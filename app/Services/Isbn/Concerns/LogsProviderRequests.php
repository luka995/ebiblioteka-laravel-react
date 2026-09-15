<?php

namespace App\Services\Isbn\Concerns;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Zajednicko slanje HTTP zahteva ka ISBN provajderima uz identifikaciju i log.
 *
 * Loguje se svaki zahtev (debug), neuspeh (warning) i prazan rezultat (info).
 * Google API kljuc se nikada ne upisuje u log.
 */
trait LogsProviderRequests
{
    /**
     * @param  array<string, mixed>  $query
     * @param  array<string, string>  $headers
     * @param  array<string, mixed>  $context
     */
    protected function send(
        string $provider,
        string $isbn,
        string $url,
        array $query = [],
        array $headers = [],
        array $context = [],
    ): ?Response {
        $hasKey = isset($query['key']) && $query['key'] !== '';
        unset($query['key']);

        Log::debug('ISBN provider request', array_merge([
            'provider' => $provider,
            'isbn' => $isbn,
            'url' => $url,
            'query' => $query,
            'has_key' => $hasKey,
        ], $context));

        try {
            $response = Http::timeout((int) config('isbn.timeout', 8))
                ->withHeaders(array_merge(['User-Agent' => $this->isbnUserAgent()], $headers))
                ->get($url, $query);
        } catch (Throwable $exception) {
            Log::warning('ISBN provider failed', array_merge([
                'provider' => $provider,
                'isbn' => $isbn,
                'reason' => 'exception',
                'error' => $exception->getMessage(),
            ], $context));

            return null;
        }

        if (! $response->successful()) {
            Log::warning('ISBN provider failed', array_merge([
                'provider' => $provider,
                'isbn' => $isbn,
                'reason' => $this->looksBlocked($response->body()) ? 'blocked' : 'http_error',
                'status' => $response->status(),
                'body' => $this->bodySnippet($response->body()),
            ], $context));

            return null;
        }

        return $response;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    protected function logEmpty(string $provider, string $isbn, string $reason, array $context = []): void
    {
        Log::info('ISBN provider returned no metadata', array_merge([
            'provider' => $provider,
            'isbn' => $isbn,
            'reason' => $reason,
        ], $context));
    }

    protected function logSkipped(string $provider, string $isbn, string $reason): void
    {
        Log::debug('ISBN provider skipped', [
            'provider' => $provider,
            'isbn' => $isbn,
            'reason' => $reason,
        ]);
    }

    protected function logResolved(string $provider, string $isbn, ?string $title): void
    {
        Log::debug('ISBN provider resolved', [
            'provider' => $provider,
            'isbn' => $isbn,
            'title' => $title,
        ]);
    }

    protected function isbnUserAgent(): string
    {
        return (string) config('isbn.user_agent', 'eBiblioteka/1.0 (+mailto:akademijafilipovic@gmail.com)');
    }

    private function bodySnippet(string $body): ?string
    {
        $body = trim((string) preg_replace('/\s+/', ' ', $body));

        return $body === '' ? null : mb_substr($body, 0, 200);
    }

    private function looksBlocked(string $body): bool
    {
        $body = mb_strtolower($body);

        return str_contains($body, 'anubis') || str_contains($body, 'not a bot') || str_contains($body, 'making sure');
    }
}
