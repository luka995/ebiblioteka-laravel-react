<?php

namespace App\Services\Isbn;

use App\Services\Isbn\Concerns\LogsProviderRequests;
use DOMDocument;
use DOMNode;
use DOMXPath;

/**
 * Scraping COBISS+ (uzajamni katalog / NBS) kao primarni ISBN izvor.
 *
 * Dvokoracni tok: HTML pretraga po ISBN-u vraca listu pogodaka, a detalj se
 * cita kao JSON sa /bib/{database}/{id}/full. Prihvata se samo zapis ciji se
 * ISBN poklapa sa trazenim, cime se izbegava pogresna knjiga.
 * Postuje robots.txt (Crawl-delay 1) i identifikuje se kroz User-Agent.
 */
class NbsCatalogProvider implements IsbnProvider
{
    use LogsProviderRequests;

    public function __construct(private readonly IsbnNormalizer $normalizer) {}

    public function name(): string
    {
        return 'nbs';
    }

    public function lookup(string $isbn): ?BookMetadata
    {
        $normalized = $this->normalizer->normalize($isbn);

        if (! config('isbn.nbs.enabled')) {
            $this->logSkipped($this->name(), $normalized, 'disabled');

            return null;
        }

        $searchUrl = trim((string) config('isbn.nbs.search_url'));

        if ($searchUrl === '') {
            $this->logSkipped($this->name(), $normalized, 'no_search_url');

            return null;
        }

        $query = array_merge(
            (array) config('isbn.nbs.search_params', []),
            [(string) config('isbn.nbs.query_param', 'q') => $normalized],
        );

        $html = $this->fetch($searchUrl, $query, 'search', $normalized);

        if ($html === null) {
            return null;
        }

        $rows = $this->rows($this->document($html));

        if ($rows === []) {
            $this->logEmpty($this->name(), $normalized, 'no_hits');

            return null;
        }

        return $this->match($rows, $normalized);
    }

    /**
     * @param  array<int, array{href: string, title: string|null, author: string|null, year: string|null}>  $rows
     */
    private function match(array $rows, string $isbn): ?BookMetadata
    {
        $maxResults = max(1, (int) config('isbn.nbs.max_results', 3));
        $delay = (float) config('isbn.nbs.crawl_delay', 1);

        foreach (array_slice($rows, 0, $maxResults) as $index => $row) {
            if ($index > 0 && $delay > 0) {
                usleep((int) ($delay * 1_000_000));
            }

            $recordId = $this->recordId($row['href']);
            $fullUrl = $recordId === null ? null : $this->fullUrl($recordId);
            $json = $fullUrl === null ? null : $this->fetchJson($fullUrl, $isbn);

            if ($json === null) {
                continue;
            }

            $detail = $this->detailFromJson($json);

            if (! $this->isbnMatches($detail['isbn'] ?? null, $isbn)) {
                $this->logEmpty($this->name(), $isbn, 'isbn_mismatch', [
                    'stage' => 'detail',
                    'record' => $row['href'],
                ]);

                continue;
            }

            $metadata = $this->metadata($row, $detail, $isbn);
            $this->logResolved($this->name(), $isbn, $metadata->title);

            return $metadata;
        }

        $this->logEmpty($this->name(), $isbn, 'isbn_mismatch');

        return null;
    }

    /**
     * @return array<int, array{href: string, title: string|null, author: string|null, year: string|null}>
     */
    private function rows(DOMDocument $document): array
    {
        $xpath = new DOMXPath($document);
        $expressions = (array) config('isbn.nbs.xpaths', []);
        $nodes = $xpath->query((string) ($expressions['result_row'] ?? ''));

        if ($nodes === false) {
            return [];
        }

        $rows = [];

        foreach ($nodes as $node) {
            $href = $this->text($xpath, $node, (string) ($expressions['result_href'] ?? ''));

            if ($href === null) {
                continue;
            }

            $rows[] = [
                'href' => $href,
                'title' => $this->text($xpath, $node, (string) ($expressions['result_title'] ?? '')),
                'author' => $this->text($xpath, $node, (string) ($expressions['result_author'] ?? '')),
                'year' => $this->text($xpath, $node, (string) ($expressions['result_year'] ?? '')),
            ];
        }

        return $rows;
    }

    /**
     * Pretraga COBISS+ kataloga po proizvoljnom upitu (koristi harvest).
     *
     * @param  array<string, mixed>  $params  Dopunske pretrage parametre (npr. db, mat).
     * @return array{total: int, rows: array<int, array{id: string, title: string|null, author: string|null, year: string|null}>}
     */
    public function search(string $query, int $start = 0, array $params = []): array
    {
        if (! config('isbn.nbs.enabled')) {
            return ['total' => 0, 'rows' => []];
        }

        $searchUrl = trim((string) config('isbn.nbs.search_url'));

        if ($searchUrl === '') {
            return ['total' => 0, 'rows' => []];
        }

        $queryParams = array_merge(
            (array) config('isbn.nbs.search_params', []),
            $params,
            [
                (string) config('isbn.nbs.query_param', 'q') => $query,
                'start' => max(0, $start),
            ],
        );

        $html = $this->fetch($searchUrl, $queryParams, 'search', $query);

        if ($html === null) {
            return ['total' => 0, 'rows' => []];
        }

        $rows = [];

        foreach ($this->rows($this->document($html)) as $row) {
            $id = $this->recordId($row['href']);

            if ($id !== null) {
                $rows[] = [
                    'id' => $id,
                    'title' => $row['title'],
                    'author' => $row['author'],
                    'year' => $row['year'],
                ];
            }
        }

        return ['total' => $this->totalHits($html), 'rows' => $rows];
    }

    /**
     * Dohvata metapodatke zapisa direktno po COBISS ID-u (bez ISBN gejta).
     *
     * Koristi se za harvest, gde je zapis pronadjen pretragom po upitu, a
     * detalji se citaju iz `/full` JSON odgovora.
     */
    public function lookupRecord(string $recordId): ?BookMetadata
    {
        $recordId = trim($recordId);

        if ($recordId === '' || ! ctype_digit($recordId)) {
            return null;
        }

        if (! config('isbn.nbs.enabled')) {
            return null;
        }

        $fullUrl = $this->fullUrl($recordId);

        if ($fullUrl === null) {
            return null;
        }

        $json = $this->fetchJson($fullUrl, $recordId);

        if ($json === null) {
            return null;
        }

        $detail = $this->detailFromJson($json);
        $isbn = $this->firstIsbn($detail['isbn']);

        $metadata = $this->metadata(
            ['href' => "bib/{$recordId}", 'title' => null, 'author' => null, 'year' => null],
            $detail,
            $isbn,
        );

        $this->logResolved($this->name(), $isbn ?? $recordId, $metadata->title);

        return $metadata;
    }

    /**
     * @param  array<string, mixed>  $json
     * @return array{title: string|null, author: string|null, isbn: string|null, production: string|null, publish_year: string|null, physical: string|null, category: string|null, udk: string|null, note: string|null, language: string|null}
     */
    private function detailFromJson(array $json): array
    {
        return [
            'title' => $this->stripStatement($this->clean($this->field($json, 'titleCard'))),
            'author' => $this->clean($this->field($json, 'author700701')),
            'isbn' => $this->clean($this->field($json, 'isbnCard')),
            'production' => $this->clean($this->field($json, 'publisherCard')),
            'publish_year' => $this->clean($this->field($json, 'publishDate')),
            'physical' => $this->clean($this->field($json, 'ph_descriptionCard')),
            'category' => $this->clean($this->field($json, 'materialDescr')),
            'udk' => $this->firstLine($this->cleanMultiline($this->field($json, 'udkCard'))),
            'note' => $this->cleanMultiline($this->field($json, 'notesCard')),
            'language' => $this->clean($this->field($json, 'languageCard')),
        ];
    }

    /**
     * @param  array<string, mixed>  $json
     */
    private function field(array $json, string $key): ?string
    {
        $value = $json[$key]['value'] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * @param  array{href: string, title: string|null, author: string|null, year: string|null}  $row
     * @param  array{title: string|null, author: string|null, isbn: string|null, production: string|null, publish_year: string|null, physical: string|null, category: string|null, udk: string|null, note: string|null, language: string|null}  $detail
     */
    private function metadata(array $row, array $detail, ?string $isbn): BookMetadata
    {
        [$place, $publisher, $year] = $this->production($detail['production']);

        return new BookMetadata(
            source: $this->name(),
            isbn: $isbn,
            title: $detail['title'] ?? $row['title'],
            authors: $this->authors($detail['author'] ?? $row['author']),
            publisher: $publisher,
            publishPlace: $place,
            publishYear: $detail['publish_year'] ?? $year ?? $row['year'],
            pages: $this->pages($detail['physical']),
            dimensions: $this->dimensions($detail['physical']),
            description: $detail['note'],
            coverUrl: null,
            category: $detail['category'],
            udk: $detail['udk'],
            language: $detail['language'],
        );
    }

    /**
     * "Београд : Задужбина Иве Андрића, 2018 (Нови Сад : Сајнос)" -> [место, издавач, година].
     *
     * @return array{0: string|null, 1: string|null, 2: string|null}
     */
    private function production(?string $value): array
    {
        if ($value === null || $value === '') {
            return [null, null, null];
        }

        // Ukloni zavrsnu zagradu (printer) pre parsiranja.
        $rest = trim((string) preg_replace('/\s*\([^)]*\)\s*$/u', '', trim($value)));

        $place = null;

        if (str_contains($rest, ':')) {
            [$placePart, $restPart] = explode(':', $rest, 2);
            $place = trim($placePart) ?: null;
            $rest = trim($restPart);
        }

        $year = preg_match('/\b(1[5-9]\d{2}|20\d{2})\b/u', $rest, $matches) ? $matches[1] : null;
        $publisher = trim((string) preg_replace('/\s*,\s*(1[5-9]\d{2}|20\d{2})\s*$/u', '', $rest));

        if ($publisher === '') {
            $publisher = null;
        }

        return [$place, $publisher, $year];
    }

    /**
     * "Андрић, Иво, 1892-1975 = Andrić, Ivo, 1892-1975" -> ["Андрић, Иво"].
     *
     * @return array<int, string>
     */
    private function authors(?string $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        $authors = [];

        foreach (preg_split('/\s*;\s*/u', $value) ?: [] as $part) {
            $part = trim($part);

            if ($part === '') {
                continue;
            }

            if (str_contains($part, '=')) {
                $part = trim(explode('=', $part, 2)[0]);
            }

            $part = trim((string) preg_replace('/\s*,\s*\d{3,4}\s*[-–]?\s*(\d{3,4})?\s*$/u', '', $part));

            if ($part !== '') {
                $authors[] = $part;
            }
        }

        return array_values(array_unique($authors));
    }

    private function pages(?string $value): ?int
    {
        if ($value !== null && preg_match('/(\d+)\s*(?:str|стр)\.?/iu', $value, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }

    private function dimensions(?string $value): ?string
    {
        if ($value !== null && preg_match('/(\d+(?:\s*[x×]\s*\d+)?)\s*cm/u', $value, $matches)) {
            return trim($matches[1]).' cm';
        }

        return null;
    }

    private function isbnMatches(?string $candidates, string $expected): bool
    {
        if ($candidates === null || $expected === '') {
            return false;
        }

        foreach (preg_split('/[\s,;\/]+/u', $candidates) ?: [] as $candidate) {
            if ($candidate !== '' && $this->normalizer->normalize($candidate) === $expected) {
                return true;
            }
        }

        return $this->normalizer->normalize($candidates) === $expected;
    }

    private function clean(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $text = preg_replace('/<br\s*\/?>/i', ' ', $value) ?? $value;
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = trim((string) preg_replace('/\s+/u', ' ', $text));

        return $text === '' ? null : $text;
    }

    private function cleanMultiline(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $text = preg_replace('/<br\s*\/?>/i', "\n", $value) ?? $value;
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $lines = array_values(array_filter(
            array_map('trim', explode("\n", $text)),
            fn (string $line): bool => $line !== '',
        ));

        return $lines === [] ? null : implode("\n", $lines);
    }

    private function firstLine(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $first = trim(explode("\n", $value)[0]);

        return $first === '' ? null : $first;
    }

    private function stripStatement(?string $title): ?string
    {
        if ($title === null || $title === '') {
            return null;
        }

        $parts = preg_split('/\s+\/\s+/u', $title, 2);
        $title = trim($parts[0] ?? $title);

        return $title === '' ? null : $title;
    }

    /**
     * Prvi validan ISBN iz polja (moze sadrzati vise ISBN-a i opis poveza).
     */
    private function firstIsbn(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        foreach (preg_split('/[\s,;\/]+/u', $value) ?: [] as $candidate) {
            $normalized = $this->normalizer->normalize($candidate);

            if ($normalized !== '' && $this->normalizer->isValid($normalized)) {
                return $normalized;
            }
        }

        return null;
    }

    private function totalHits(string $html): int
    {
        return preg_match('/data-hits="(\d+)"/', $html, $matches) ? (int) $matches[1] : 0;
    }

    private function recordId(string $href): ?string
    {
        return preg_match('/(\d+)\s*$/u', trim($href), $matches) ? $matches[1] : null;
    }

    private function fullUrl(string $recordId): ?string
    {
        $base = trim((string) config('isbn.nbs.base_url', ''));
        $path = trim((string) config('isbn.nbs.full_path', 'bib/{database}/{id}/full'));

        if ($base === '' || $path === '') {
            return null;
        }

        $path = str_replace(
            ['{database}', '{id}'],
            [(string) config('isbn.nbs.record_database', 'COBIB'), $recordId],
            $path,
        );

        return rtrim($base, '/').'/'.ltrim($path, '/');
    }

    private function text(DOMXPath $xpath, DOMNode $context, string $expression): ?string
    {
        if ($expression === '') {
            return null;
        }

        $nodes = $xpath->query($expression, $context);

        if ($nodes === false || $nodes->length === 0) {
            return null;
        }

        $text = trim($nodes->item(0)->textContent);

        return $text === '' ? null : $text;
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function fetch(string $url, array $query, string $stage, string $isbn): ?string
    {
        $response = $this->send(
            $this->name(),
            $isbn,
            $url,
            $query,
            [
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language' => 'sr,en;q=0.8',
            ],
            ['stage' => $stage],
        );

        if ($response === null) {
            return null;
        }

        $body = trim($response->body());

        return $body === '' ? null : $body;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchJson(string $url, string $isbn): ?array
    {
        $response = $this->send(
            $this->name(),
            $isbn,
            $url,
            [],
            [
                'Accept' => 'application/json',
                'Accept-Language' => 'sr,en;q=0.8',
            ],
            ['stage' => 'detail'],
        );

        if ($response === null) {
            return null;
        }

        $data = $response->json();

        if (! is_array($data) || $data === []) {
            $this->logEmpty($this->name(), $isbn, 'invalid_json', ['stage' => 'detail']);

            return null;
        }

        return $data;
    }

    private function document(string $html): DOMDocument
    {
        $document = new DOMDocument;
        libxml_use_internal_errors(true);
        // Eksplicitni UTF-8 da DOMDocument ne tumaci bajtove kao ISO-8859-1.
        $document->loadHTML('<?xml encoding="UTF-8" ?>'.$html);
        libxml_clear_errors();

        return $document;
    }
}
