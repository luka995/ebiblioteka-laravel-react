<?php

use App\DTOs\IsbnLookupResultDTO;
use App\Enums\IsbnLookupStatusEnum;
use App\Services\Isbn\BookMetadata;
use App\Services\Isbn\IsbnMetadataService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

function importLookupSearch(int $hits = 1): string
{
    return '<html><body><div data-hits="'.$hits.'"><table>'
        .($hits === 0 ? '' : '<tr class="biblioentry" data-href="bib/123"><td><a class="title">COBISS title</a></td></tr>')
        .'</table></div></body></html>';
}

function importLookupDetail(string $isbn = '9788681131213'): array
{
    return [
        'isbnCard' => ['value' => $isbn],
        'titleCard' => ['value' => 'COBISS title'],
        'author700701' => ['value' => 'Андрић, Иво, 1892-1975'],
    ];
}

beforeEach(function () {
    Cache::flush();
    Http::preventStrayRequests();
    config([
        'isbn.nbs.enabled' => true,
        'isbn.nbs.search_url' => 'https://cobiss.test/search',
        'isbn.nbs.base_url' => 'https://cobiss.test/',
        'isbn.nbs.crawl_delay' => 0,
        'isbn.http_retry_delay_ms' => 0,
    ]);
});

test('import lookup uses only COBISS and its own canonical import-scoped cache', function () {
    Cache::put('isbn:metadata:9788681131213', new BookMetadata('google_books', title: 'Untrusted title'));
    Http::fake([
        'cobiss.test/search*' => Http::response(importLookupSearch()),
        'cobiss.test/bib/COBIB/123/full' => Http::response(importLookupDetail()),
    ]);
    $service = app(IsbnMetadataService::class);
    $result = $service->lookupForImport('978-86-81131-21-3', 11);

    expect($result->status)->toBe(IsbnLookupStatusEnum::Found)
        ->and($result->metadata->source)->toBe('nbs')
        ->and($result->metadata->title)->toBe('COBISS title');
    $service->lookupForImport('8681131214', 11);
    Http::assertSentCount(2);
    $service->lookupForImport('9788681131213', 12);
    Http::assertSentCount(4);
    Http::assertSent(fn ($request) => $request->hasHeader('Accept-Language', 'sr,en;q=0.8'));
});

test('confirmed no result is cached per import but does not trigger other providers', function () {
    Http::fake(['cobiss.test/search*' => Http::response(importLookupSearch(0))]);
    $service = app(IsbnMetadataService::class);

    expect($service->lookupForImport('9788681131213', 11)->status)->toBe(IsbnLookupStatusEnum::NotFound)
        ->and($service->lookupForImport('9788681131213', 11)->status)->toBe(IsbnLookupStatusEnum::NotFound);
    Http::assertSentCount(1);
    $service->lookupForImport('9788681131213', 12);
    Http::assertSentCount(2);
});

test('import lookup differentiates HTTP failures and retries only transient failures', function (int $status, string $reason, int $attempts) {
    Http::fake(['cobiss.test/*' => Http::response('private response body', $status)]);
    $result = app(IsbnMetadataService::class)->lookupForImport('9788681131213', 11);

    expect($result->status)->toBe(IsbnLookupStatusEnum::Failed)
        ->and($result->diagnostics['reason'])->toBe($reason)
        ->and($result->diagnostics['http_status'])->toBe($status)
        ->and($result->diagnostics['http_attempts'])->toBe($attempts)
        ->and(json_encode($result->diagnostics))->not->toContain('private response body');
    Http::assertSentCount($attempts);
})->with([[429, 'rate_limited', 1], [403, 'http_error', 1], [503, 'http_error', 3]]);

test('connection and timeout failures retain safe diagnostics and are not cached', function (string $message, string $reason) {
    $calls = 0;
    Http::fake(function () use (&$calls, $message) {
        $calls++;
        throw new ConnectionException($message);
    });
    $service = app(IsbnMetadataService::class);

    expect($service->lookupForImport('9788681131213', 11)->diagnostics['reason'])->toBe($reason);
    $service->lookupForImport('9788681131213', 11);
    expect($calls)->toBe(6);
})->with([['cURL error 28: timed out', 'timeout'], ['private connection details', 'connection_error']]);

test('malformed or incomplete search responses are failures rather than not found', function (string $html, string $reason) {
    Http::fake([
        'cobiss.test/search*' => Http::response($html),
        'cobiss.test/bib/*' => Http::response(importLookupDetail('9780140328721')),
    ]);
    $result = app(IsbnMetadataService::class)->lookupForImport('9788681131213', 11);

    expect($result->status)->toBe(IsbnLookupStatusEnum::Failed)
        ->and($result->diagnostics['reason'])->toBe($reason);
})->with([
    ['', 'parse_error'],
    ['<html>New layout</html>', 'parse_error'],
    [importLookupSearch(5), 'incomplete_results'],
]);

test('invalid detail response is a typed lookup failure', function () {
    Http::fake([
        'cobiss.test/search*' => Http::response(importLookupSearch()),
        'cobiss.test/bib/*' => Http::response(['unexpected' => 'shape']),
    ]);
    $result = app(IsbnMetadataService::class)->lookupForImport('9788681131213', 11);
    expect($result->status)->toBe(IsbnLookupStatusEnum::Failed)
        ->and($result->diagnostics['reason'])->toBe('invalid_response');
});

test('all mismatched ISBN candidates mean not found and equivalent ISBN10 details are accepted', function () {
    Http::fake([
        'cobiss.test/search*' => Http::response(importLookupSearch()),
        'cobiss.test/bib/*' => Http::sequence()->push(importLookupDetail('9780140328721'))->push(importLookupDetail('8681131214')),
    ]);
    $service = app(IsbnMetadataService::class);
    expect($service->lookupForImport('9788681131213', 11)->status)->toBe(IsbnLookupStatusEnum::NotFound)
        ->and($service->lookupForImport('9788681131213', 12)->status)->toBe(IsbnLookupStatusEnum::Found);
});

test('disabled or misconfigured COBISS cannot return cached success', function (string $key, mixed $value) {
    Cache::put('isbn:imports:nbs:11:9788681131213', new IsbnLookupResultDTO(
        IsbnLookupStatusEnum::Found, new BookMetadata('nbs', '9788681131213', 'Old title'), ['provider' => 'nbs'],
    ));
    config([$key => $value]);
    Http::fake();
    $result = app(IsbnMetadataService::class)->lookupForImport('9788681131213', 11);

    expect($result->status)->toBe(IsbnLookupStatusEnum::Failed)
        ->and($result->diagnostics['reason'])->toBe('configuration_error');
    Http::assertNothingSent();
})->with([['isbn.nbs.enabled', false], ['isbn.nbs.search_url', '']]);
