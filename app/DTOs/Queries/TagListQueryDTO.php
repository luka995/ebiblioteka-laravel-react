<?php

namespace App\DTOs\Queries;

final readonly class TagListQueryDTO
{
    public function __construct(
        public SortingDTO $sorting,
        public ?string $search = null,
        public ?int $libraryId = null,
        public int $perPage = 25,
    ) {}
}
