<?php

namespace App\DTOs\Queries;

final readonly class BookListQueryDTO
{
    public function __construct(
        public SortingDTO $sorting,
        public ?string $search = null,
        public ?string $isbn = null,
        public ?int $categoryId = null,
        public int $perPage = 25,
    ) {}
}
