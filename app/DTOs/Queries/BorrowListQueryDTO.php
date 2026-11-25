<?php

namespace App\DTOs\Queries;

final readonly class BorrowListQueryDTO
{
    public function __construct(
        public SortingDTO $sorting,
        public ?string $status = null,
        public ?string $search = null,
        public ?string $dateFrom = null,
        public ?string $dateTo = null,
        public bool $overdue = false,
        public int $perPage = 25,
    ) {}
}
