<?php

namespace App\Services\Isbn\Concerns;

use App\DTOs\IsbnLookupResultDTO;
use App\Enums\IsbnLookupStatusEnum;
use App\Exceptions\IsbnLookupException;
use App\Services\Isbn\BookMetadata;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

trait LooksUpImportMetadata
{
    public function importConfigured(): bool
    {
        return (bool) config('isbn.nbs.enabled')
            && filter_var(config('isbn.nbs.search_url'), FILTER_VALIDATE_URL) !== false
            && filter_var(config('isbn.nbs.base_url'), FILTER_VALIDATE_URL) !== false
            && trim((string) config('isbn.nbs.full_path')) !== ''
            && trim((string) config('isbn.nbs.xpaths.result_row')) !== '';
    }

    public function lookupForImport(string $isbn): IsbnLookupResultDTO
    {
        $isbn = $this->normalizer->normalize($isbn);
        $attempts = 0;
        $httpStatus = null;
        $stage = 'search';

        try {
            if (! $this->importConfigured() || ! $this->normalizer->isValid($isbn)) {
                throw new IsbnLookupException('configuration_error');
            }

            $response = $this->importRequest(
                config('isbn.nbs.search_url'),
                array_merge((array) config('isbn.nbs.search_params'), [config('isbn.nbs.query_param', 'q') => $isbn]),
                'text/html',
                $attempts,
                $httpStatus,
            );
            $html = trim($response->body());

            if ($html === '') {
                throw new IsbnLookupException('parse_error');
            }

            $previous = libxml_use_internal_errors(true);

            try {
                $document = $this->document($html);
                $xpath = new \DOMXPath($document);

                if ($xpath->query(config('isbn.nbs.xpaths.result_row')) === false) {
                    throw new IsbnLookupException('configuration_error');
                }

                $rows = $this->rows($document);
            } finally {
                libxml_clear_errors();
                libxml_use_internal_errors($previous);
            }

            $hasTotal = preg_match('/\bdata-hits\s*=\s*["\'](\d+)["\']/i', $html, $hits) === 1;
            $total = $hasTotal ? (int) $hits[1] : null;

            if ($rows === []) {
                if ($total === 0) {
                    return $this->importResult(IsbnLookupStatusEnum::NotFound, null, null, $httpStatus, $attempts, $stage);
                }

                throw new IsbnLookupException('parse_error');
            }

            $stage = 'detail';
            $checked = 0;

            foreach (array_slice($rows, 0, max(1, (int) config('isbn.nbs.max_results', 3))) as $row) {
                $id = $this->recordId($row['href']);
                $url = $id === null ? null : $this->fullUrl($id);

                if ($url === null) {
                    throw new IsbnLookupException('parse_error');
                }

                $response = $this->importRequest($url, [], 'application/json', $attempts, $httpStatus);
                $json = $response->json();

                if (! is_array($json) || ! is_string($json['isbnCard']['value'] ?? null)) {
                    throw new IsbnLookupException('invalid_response');
                }

                $detail = $this->detailFromJson($json);
                $checked++;
                $matches = false;

                foreach ($this->normalizer->forms($isbn) as $form) {
                    $matches = $matches || $this->isbnMatches($detail['isbn'], $form);
                }

                if ($matches) {
                    return $this->importResult(
                        IsbnLookupStatusEnum::Found,
                        $this->metadata($row, $detail, $isbn),
                        null,
                        $httpStatus,
                        $attempts,
                        $stage,
                    );
                }
            }

            if ($total !== null && $checked >= $total) {
                return $this->importResult(IsbnLookupStatusEnum::NotFound, null, 'isbn_mismatch', $httpStatus, $attempts, $stage);
            }

            throw new IsbnLookupException('incomplete_results');
        } catch (IsbnLookupException $e) {
            return $this->importResult(IsbnLookupStatusEnum::Failed, null, $e->reason, $httpStatus, $attempts, $stage);
        }
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function importRequest(string $url, array $query, string $accept, int &$attempts, ?int &$httpStatus): Response
    {
        $retries = max(0, (int) config('isbn.http_retries', 2));
        $delay = max(0, (float) config('isbn.nbs.crawl_delay', 1));

        for ($try = 0; $try <= $retries; $try++) {
            if ($delay > 0) {
                usleep((int) ($delay * 1_000_000));
            }

            if ($try > 0) {
                usleep(max(0, (int) config('isbn.http_retry_delay_ms', 250)) * 1000);
            }

            $attempts++;
            $httpStatus = null;

            try {
                $response = Http::timeout((int) config('isbn.timeout', 8))
                    ->withHeaders([
                        'User-Agent' => $this->isbnUserAgent(),
                        'Accept' => $accept,
                        'Accept-Language' => 'sr,en;q=0.8',
                    ])->get($url, $query);
            } catch (ConnectionException $e) {
                if ($try < $retries) {
                    continue;
                }

                throw new IsbnLookupException(
                    str_contains(strtolower($e->getMessage()), 'timed out') || str_contains($e->getMessage(), 'cURL error 28')
                        ? 'timeout' : 'connection_error',
                    $e,
                );
            }

            $httpStatus = $response->status();

            if ($response->successful()) {
                return $response;
            }

            if ($response->serverError() && $try < $retries) {
                continue;
            }

            throw new IsbnLookupException($httpStatus === 429 ? 'rate_limited' : 'http_error');
        }

        throw new IsbnLookupException('connection_error');
    }

    private function importResult(
        IsbnLookupStatusEnum $status,
        ?BookMetadata $metadata,
        ?string $reason,
        ?int $httpStatus,
        int $attempts,
        string $stage,
    ): IsbnLookupResultDTO {
        $diagnostics = [
            'provider' => 'nbs',
            'status' => $status->value,
            'reason' => $reason,
            'http_status' => $httpStatus,
            'http_attempts' => $attempts,
            'stage' => $stage,
        ];
        Log::channel(config('book_imports.log_channel'))->log(
            $status === IsbnLookupStatusEnum::Failed ? 'warning' : 'info',
            'book_import.lookup',
            $diagnostics,
        );

        return new IsbnLookupResultDTO($status, $metadata, $diagnostics);
    }
}
