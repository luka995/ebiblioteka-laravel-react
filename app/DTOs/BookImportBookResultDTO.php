<?php

namespace App\DTOs;

use App\Models\Book;

final readonly class BookImportBookResultDTO
{
    /**
     * @param  array<string, string>  $sources
     */
    public function __construct(public Book $book, public array $sources) {}
}
