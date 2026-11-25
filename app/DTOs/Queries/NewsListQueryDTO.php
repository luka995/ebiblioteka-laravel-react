<?php

namespace App\DTOs\Queries;

final readonly class NewsListQueryDTO
{
    public function __construct(
        public SortingDTO $sorting,
        public ?string $search = null,
        public int $perPage = 25,
    ) {}
}
