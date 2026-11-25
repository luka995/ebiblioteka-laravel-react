<?php

namespace App\DTOs\Queries;

final readonly class SchoolClassStudentListQueryDTO
{
    public function __construct(
        public SortingDTO $sorting,
        public int $perPage = 25,
    ) {}
}
