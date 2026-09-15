<?php

namespace App\Services;

use App\Models\Author;
use App\Models\Book;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * Upis naslova (books) i povezivanje autora.
 *
 * Autori se zadaju imenima (kao u legacy formi i ISBN toku): postojeci autor u
 * istoj biblioteci se koristi, a novi se kreira.
 */
class BookService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Book
    {
        return DB::transaction(function () use ($data): Book {
            /** @var Book $book */
            $book = Book::create(Arr::except($data, ['authors']));
            $this->syncAuthors($book, $data['authors'] ?? []);

            return $book;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Book $book, array $data): Book
    {
        return DB::transaction(function () use ($book, $data): Book {
            $book->update(Arr::except($data, ['authors']));

            if (array_key_exists('authors', $data)) {
                $this->syncAuthors($book, $data['authors'] ?? []);
            }

            return $book;
        });
    }

    /**
     * @param  array<int, string|null>  $names
     */
    private function syncAuthors(Book $book, array $names): void
    {
        $ids = collect($names)
            ->map(fn ($name): string => trim((string) $name))
            ->filter()
            ->unique()
            ->map(fn (string $name): int => Author::firstOrCreate([
                'library_id' => $book->library_id,
                'name' => $name,
            ])->id)
            ->all();

        $book->authors()->sync($ids);
    }
}
