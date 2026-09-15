<?php

namespace App\Services\Isbn;

use App\Services\Isbn\Concerns\LogsProviderRequests;

/**
 * Open Library Books API (primarni izvor metapodataka).
 */
class OpenLibraryProvider implements IsbnProvider
{
    use LogsProviderRequests;

    public function name(): string
    {
        return 'open_library';
    }

    public function lookup(string $isbn): ?BookMetadata
    {
        $response = $this->send(
            $this->name(),
            $isbn,
            'https://openlibrary.org/api/books',
            [
                'bibkeys' => "ISBN:{$isbn}",
                'format' => 'json',
                'jscmd' => 'data',
            ],
            ['Accept' => 'application/json'],
        );

        if ($response === null) {
            return null;
        }

        $payload = $response->json("ISBN:{$isbn}");

        if (! is_array($payload) || empty($payload['title'])) {
            $this->logEmpty($this->name(), $isbn, 'no_title');

            return null;
        }

        $this->logResolved($this->name(), $isbn, $payload['title'] ?? null);

        return new BookMetadata(
            source: $this->name(),
            isbn: $isbn,
            title: $payload['title'] ?? null,
            authors: $this->names($payload['authors'] ?? []),
            publisher: $this->first($payload['publishers'] ?? []),
            publishPlace: $this->first($payload['publish_places'] ?? []),
            publishYear: $this->year($payload['publish_date'] ?? null),
            pages: isset($payload['number_of_pages']) ? (int) $payload['number_of_pages'] : null,
            dimensions: $payload['dimensions'] ?? null,
            description: $payload['notes'] ?? $payload['by_statement'] ?? null,
            coverUrl: $payload['cover']['large'] ?? $payload['cover']['medium'] ?? $payload['cover']['small'] ?? null,
            category: $this->first($payload['subjects'] ?? []),
        );
    }

    /**
     * @param  array<int, array{name?: string}>  $values
     * @return array<int, string>
     */
    private function names(array $values): array
    {
        $names = [];

        foreach ($values as $value) {
            if (isset($value['name']) && $value['name'] !== '') {
                $names[] = (string) $value['name'];
            }
        }

        return $names;
    }

    /**
     * @param  array<int, array{name?: string}>  $values
     */
    private function first(array $values): ?string
    {
        return isset($values[0]['name']) ? (string) $values[0]['name'] : null;
    }

    private function year(?string $date): ?string
    {
        if ($date === null) {
            return null;
        }

        return preg_match('/\d{4}/', $date, $matches) ? $matches[0] : $date;
    }
}
