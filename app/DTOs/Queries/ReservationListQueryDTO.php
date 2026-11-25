<?php

namespace App\DTOs\Queries;

final readonly class ReservationListQueryDTO
{
    public function __construct(
        public SortingDTO $sorting,
        public ?string $status = null,
        public ?string $search = null,
        public ?string $dateFrom = null,
        public ?string $dateTo = null,
        public int $perPage = 25,
    ) {}
}
