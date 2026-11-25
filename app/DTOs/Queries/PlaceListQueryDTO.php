<?php

namespace App\DTOs\Queries;

final readonly class PlaceListQueryDTO
{
    public function __construct(
        public SortingDTO $sorting,
        public ?string $search = null,
        public ?int $regionId = null,
        public int $perPage = 25,
    ) {}
}
