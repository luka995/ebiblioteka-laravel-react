<?php

namespace App\Services\Isbn;

use App\DTOs\IsbnLookupResultDTO;
use App\Enums\IsbnLookupStatusEnum;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Orkestrator ISBN lookup-a: prolazi kroz izvore po prioritetu uz kesiranje.
 */
class IsbnMetadataService
{
    /**
     * @param  array<int, IsbnProvider>  $providers
     */
    public function __construct(
        private readonly IsbnNormalizer $normalizer,
        private readonly array $providers,
        private readonly CategoryTranslator $translator,
    ) {}

    public function normalizer(): IsbnNormalizer
    {
        return $this->normalizer;
    }

    public function lookup(string $isbn): ?BookMetadata
    {
        $normalized = $this->normalizer->normalize($isbn);

        if (! $this->normalizer->isValid($normalized)) {
            return null;
        }

        $key = "isbn:metadata:{$normalized}";
        $cached = Cache::get($key);

        if ($cached instanceof BookMetadata) {
            return $cached;
        }

        $metadata = $this->resolve($normalized);

        // Negativni rezultati se ne kesiraju: tranzijentni pad provajdera ne sme
        // da zakljuca ISBN na duzi period.
        if ($metadata !== null) {
            Cache::put($key, $metadata, (int) config('isbn.cache_ttl', 86400));
        }

        return $metadata;
    }

    public function lookupForImport(string $isbn, int $importId): IsbnLookupResultDTO
    {
        $provider = collect($this->providers)->first(fn (IsbnProvider $provider) => $provider instanceof NbsCatalogProvider);

        if (! $provider instanceof NbsCatalogProvider) {
            Log::channel(config('book_imports.log_channel'))->error('book_import.cobiss_not_configured');

            return new IsbnLookupResultDTO(IsbnLookupStatusEnum::Failed, null, [
                'provider' => 'nbs', 'status' => 'failed', 'reason' => 'configuration_error', 'http_attempts' => 0,
            ]);
        }

        $forms = $this->normalizer->forms($isbn);
        $canonical = collect($forms)->first(fn (string $form) => strlen($form) === 13) ?? $this->normalizer->normalize($isbn);
        $key = "isbn:imports:nbs:{$importId}:{$canonical}";
        $cached = Cache::get($key);

        if ($provider->importConfigured() && $this->normalizer->isValid($canonical) && $cached instanceof IsbnLookupResultDTO
            && ($cached->diagnostics['provider'] ?? null) === 'nbs'
            && ($cached->status === IsbnLookupStatusEnum::NotFound
                || ($cached->status === IsbnLookupStatusEnum::Found && $cached->metadata?->source === 'nbs'
                    && in_array($this->normalizer->normalize($cached->metadata->isbn ?? ''), $forms, true)))) {
            return $cached;
        }

        $result = $provider->lookupForImport($canonical);

        if ($result->status !== IsbnLookupStatusEnum::Failed) {
            Cache::put($key, $result, (int) config('isbn.cache_ttl', 86400));
        }

        return $result;
    }

    private function resolve(string $isbn): ?BookMetadata
    {
        foreach ($this->providers as $provider) {
            try {
                $metadata = $provider->lookup($isbn);
            } catch (Throwable $exception) {
                Log::warning('ISBN provider failed', [
                    'provider' => $provider->name(),
                    'isbn' => $isbn,
                    'error' => $exception->getMessage(),
                ]);

                continue;
            }

            if ($metadata !== null) {
                return $this->withTranslatedCategory($metadata);
            }
        }

        return null;
    }

    /**
     * Prevede kategoriju na srpski pre kesiranja (nedostupni izvori ostavljaju
     * originalni naziv).
     */
    private function withTranslatedCategory(BookMetadata $metadata): BookMetadata
    {
        if ($metadata->category === null) {
            return $metadata;
        }

        return $metadata->withCategory($this->translator->translate($metadata->category));
    }
}
