<?php

namespace App\Services\Isbn;

/**
 * Normalizovani bibliografski podaci dobijeni iz eksternog izvora.
 */
final readonly class BookMetadata
{
    /**
     * @param  array<int, string>  $authors
     */
    public function __construct(
        public string $source,
        public ?string $isbn = null,
        public ?string $title = null,
        public array $authors = [],
        public ?string $publisher = null,
        public ?string $publishPlace = null,
        public ?string $publishYear = null,
        public ?int $pages = null,
        public ?string $dimensions = null,
        public ?string $description = null,
        public ?string $coverUrl = null,
        public ?string $category = null,
        public ?string $udk = null,
    ) {}

    public function withCategory(?string $category): self
    {
        return new self(
            source: $this->source,
            isbn: $this->isbn,
            title: $this->title,
            authors: $this->authors,
            publisher: $this->publisher,
            publishPlace: $this->publishPlace,
            publishYear: $this->publishYear,
            pages: $this->pages,
            dimensions: $this->dimensions,
            description: $this->description,
            coverUrl: $this->coverUrl,
            category: $category,
            udk: $this->udk,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'source' => $this->source,
            'isbn' => $this->isbn,
            'title' => $this->title,
            'authors' => $this->authors,
            'publisher' => $this->publisher,
            'publish_place' => $this->publishPlace,
            'publish_year' => $this->publishYear,
            'pages' => $this->pages,
            'dimensions' => $this->dimensions,
            'description' => $this->description,
            'cover_url' => $this->coverUrl,
            'category' => $this->category,
            'udk' => $this->udk,
        ];
    }
}
