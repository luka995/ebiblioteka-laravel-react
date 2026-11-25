<?php

namespace App\DTOs\Queries;

final readonly class UserListQueryDTO
{
    public function __construct(
        public SortingDTO $sorting,
        public ?string $email = null,
        public ?string $username = null,
        public ?string $firstName = null,
        public ?string $lastName = null,
        public ?string $barCode = null,
        public ?string $jmbg = null,
        public ?string $city = null,
        public ?string $role = null,
        public ?int $libraryId = null,
        public int $perPage = 25,
    ) {}
}
