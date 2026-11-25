<?php

namespace App\DTOs\Queries;

final readonly class LibraryRequestListQueryDTO
{
    public function __construct(
        public SortingDTO $sorting,
        public ?string $status = null,
        public ?string $search = null,
        public int $perPage = 25,
    ) {}
}
