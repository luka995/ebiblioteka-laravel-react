<?php

namespace App\DTOs\Queries;

final readonly class SortingDTO
{
    public function __construct(
        public ?string $sort = null,
        public ?string $direction = null,
    ) {}
}
