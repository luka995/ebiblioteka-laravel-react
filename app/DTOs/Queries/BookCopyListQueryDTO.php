<?php

namespace App\DTOs\Queries;

final readonly class BookCopyListQueryDTO
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function __construct(
        public SortingDTO $sorting,
        public array $filters = [],
        public ?int $bookId = null,
        public int $perPage = 25,
    ) {}
}
