<?php

namespace App\Services\Catalog;

use App\Services\Isbn\BookMetadata;
use App\Services\Isbn\NbsCatalogProvider;
use Illuminate\Support\Facades\Log;

/**
 * Prikuplja realne bibliografske zapise iz COBISS+ legacy kataloga.
 *
 * Dvokoracni tok: pretraga po upitima daje COBISS ID-eve, a svaki zapis se
 * cita kao `/full` JSON. Postuje se `Crawl-delay` iz robots.txt tako sto se
 * pauza uvodi izmedju svih zahteva. Rezultat je niz normalizovanih zapisa
 * pogodnih za `CobissBookSeeder`.
 */
class CobissHarvestService
{
    public function __construct(private readonly NbsCatalogProvider $provider) {}

    /**
     * @param  array<int, string>  $queries
     * @param  array<int, array<string, mixed>>  $existing  Vec prikupljeni zapisi (resume).
     * @param  callable(string, int, int):void|null  $onProgress
     * @return array<int, array<string, mixed>>
     */
    public function harvest(
        int $target,
        array $queries,
        array $existing = [],
        ?callable $onProgress = null,
    ): array {
        $target = max(1, $target);

        $records = $existing;
        $seenIds = [];
        $usedIsbn = [];
        $usedKeys = [];

        foreach ($records as $record) {
            $seenIds[(string) ($record['cobiss_id'] ?? '')] = true;

            if (! empty($record['isbn'])) {
                $usedIsbn[(string) $record['isbn']] = true;
            }

            $usedKeys[$this->dedupeKey($record)] = true;
        }

        if (count($records) >= $target) {
            return $records;
        }

        $candidates = $this->collectCandidates($queries, $target, $seenIds, $onProgress);

        foreach ($candidates as $candidate) {
            if (count($records) >= $target) {
                break;
            }

            $this->pause();

            $metadata = $this->provider->lookupRecord($candidate['id']);

            if ($metadata === null || ! $this->isAcceptable($metadata)) {
                continue;
            }

            $record = $this->normalize($candidate['id'], $metadata);

            $key = $this->dedupeKey($record);
            $isbn = $record['isbn'];

            if (isset($usedKeys[$key]) || ($isbn !== null && isset($usedIsbn[$isbn]))) {
                continue;
            }

            $usedKeys[$key] = true;

            if ($isbn !== null) {
                $usedIsbn[$isbn] = true;
            }

            $records[] = $record;
            $seenIds[(string) $candidate['id']] = true;

            if ($onProgress !== null) {
                $onProgress($record['title'] ?? $candidate['id'], count($records), $target);
            }
        }

        return $records;
    }

    /**
     * Pretrazuje katalog i prikuplja COBISS ID-eve kandidata.
     *
     * @param  array<int, string>  $queries
     * @param  array<string, bool>  $seenIds
     * @param  callable(string, int, int):void|null  $onProgress
     * @return array<int, array{id: string, title: string|null, author: string|null, year: string|null}>
     */
    private function collectCandidates(
        array $queries,
        int $target,
        array $seenIds,
        ?callable $onProgress,
    ): array {
        $pageSize = max(1, (int) config('cobiss.page_size', 10));
        $params = [
            'db' => (string) config('cobiss.database', 'cobib'),
            'mat' => (string) config('cobiss.material', 'books'),
        ];

        $candidates = [];
        $candidateLimit = $target * 3;

        foreach ($queries as $query) {
            $query = trim((string) $query);

            if ($query === '') {
                continue;
            }

            $start = 0;

            while (count($candidates) < $candidateLimit) {
                $result = $this->provider->search($query, $start, $params);
                $rows = $result['rows'];

                if ($rows === []) {
                    break;
                }

                foreach ($rows as $row) {
                    if (isset($seenIds[$row['id']])) {
                        continue;
                    }

                    $seenIds[$row['id']] = true;
                    $candidates[] = $row;
                }

                if ($onProgress !== null) {
                    $onProgress(
                        sprintf('pretraga "%s" (start %d)', $query, $start),
                        count($candidates),
                        $candidateLimit,
                    );
                }

                $start += $pageSize;

                if ($start >= (int) $result['total']) {
                    break;
                }

                $this->pause();
            }

            if (count($candidates) >= $candidateLimit) {
                break;
            }

            $this->pause();
        }

        return $candidates;
    }

    private function isAcceptable(BookMetadata $metadata): bool
    {
        if (trim((string) $metadata->title) === '') {
            return false;
        }

        $languages = array_filter((array) config('cobiss.languages', []));
        $languages = array_map(fn (string $language): string => mb_strtolower(trim($language)), $languages);

        if ($languages === []) {
            return true;
        }

        return in_array(mb_strtolower(trim((string) $metadata->language)), $languages, true);
    }

    /**
     * @return array<string, mixed>
     */
    private function normalize(string $recordId, BookMetadata $metadata): array
    {
        return [
            'cobiss_id' => $recordId,
            'isbn' => $metadata->isbn,
            'title' => $metadata->title,
            'authors' => array_values($metadata->authors),
            'publisher' => $metadata->publisher,
            'publish_place' => $metadata->publishPlace,
            'publish_year' => $metadata->publishYear,
            'pages' => $metadata->pages,
            'dimensions' => $metadata->dimensions,
            'udk' => $metadata->udk,
            'category' => $metadata->category,
            'description' => $metadata->description,
            'language' => $metadata->language,
        ];
    }

    /**
     * @param  array<string, mixed>  $record
     */
    private function dedupeKey(array $record): string
    {
        $title = mb_strtolower(trim((string) ($record['title'] ?? '')));
        $authors = (array) ($record['authors'] ?? []);
        $author = mb_strtolower(trim((string) ($authors[0] ?? '')));

        return $title.'|'.$author;
    }

    private function pause(): void
    {
        $delay = (float) config('cobiss.crawl_delay', 1);

        if ($delay <= 0) {
            return;
        }

        usleep((int) ($delay * 1_000_000));
    }

    /**
     * @param  array<int, array<string, mixed>>  $records
     */
    public function logSummary(array $records): void
    {
        Log::info('COBISS harvest completed', [
            'records' => count($records),
            'publishers' => collect($records)->pluck('publisher')->filter()->unique()->count(),
        ]);
    }
}
