<?php

namespace App\DTOs\Queries;

final readonly class LibraryListQueryDTO
{
    public function __construct(
        public SortingDTO $sorting,
        public ?string $search = null,
        public bool $deleted = false,
        public int $perPage = 25,
    ) {}
}
