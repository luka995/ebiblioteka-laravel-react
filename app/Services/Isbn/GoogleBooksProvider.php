<?php

namespace App\Services\Isbn;

use App\Services\Isbn\Concerns\LogsProviderRequests;

/**
 * Google Books API (sekundarni izvor, fallback posle Open Library-ja).
 */
class GoogleBooksProvider implements IsbnProvider
{
    use LogsProviderRequests;

    public function name(): string
    {
        return 'google_books';
    }

    public function lookup(string $isbn): ?BookMetadata
    {
        $query = ['q' => "isbn:{$isbn}"];

        if ($key = config('isbn.google_books_key')) {
            $query['key'] = $key;
        }

        $response = $this->send(
            $this->name(),
            $isbn,
            'https://www.googleapis.com/books/v1/volumes',
            $query,
            ['Accept' => 'application/json'],
        );

        if ($response === null) {
            return null;
        }

        $info = $response->json('items.0.volumeInfo');

        if (! is_array($info) || empty($info['title'])) {
            $this->logEmpty($this->name(), $isbn, 'no_items');

            return null;
        }

        $this->logResolved($this->name(), $isbn, $info['title'] ?? null);

        return new BookMetadata(
            source: $this->name(),
            isbn: $isbn,
            title: $info['title'] ?? null,
            authors: array_values(array_filter($info['authors'] ?? [])),
            publisher: $info['publisher'] ?? null,
            publishPlace: null,
            publishYear: $this->year($info['publishedDate'] ?? null),
            pages: isset($info['pageCount']) ? (int) $info['pageCount'] : null,
            dimensions: null,
            description: $info['description'] ?? null,
            coverUrl: $this->cover($info['imageLinks'] ?? []),
            category: isset($info['categories'][0]) ? (string) $info['categories'][0] : null,
        );
    }

    private function year(?string $date): ?string
    {
        return $date !== null && preg_match('/\d{4}/', $date, $matches) ? $matches[0] : $date;
    }

    /**
     * @param  array<string, string>  $links
     */
    private function cover(array $links): ?string
    {
        $url = $links['thumbnail'] ?? $links['smallThumbnail'] ?? null;

        return $url === null ? null : str_replace('http://', 'https://', $url);
    }
}
