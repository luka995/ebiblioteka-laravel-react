<?php

namespace App\DTOs\Queries;

final readonly class BookImportListQueryDTO
{
    public function __construct(
        public SortingDTO $sorting,
        public int $perPage = 25,
    ) {}
}
