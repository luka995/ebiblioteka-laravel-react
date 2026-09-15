<?php

namespace App\Support;

use App\Models\Book;
use App\Models\Category;
use App\Models\Library;

/**
 * Javni katalog: biblioteke -> kategorije -> naslovi, sa realnim podacima iz
 * baze. Zadrzava oblik koji ocekuju Blade view-ovi javnog portala.
 */
class LibraryCatalog
{
    /** @var array<int, string> */
    private const COLORS = ['#ffd968', '#83d2eb', '#dcd5f3', '#f68b72', '#a8e6a1', '#f3c7e6'];

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function all(): array
    {
        return Library::query()
            ->where('deleted', false)
            ->with('place')
            ->orderBy('name')
            ->get()
            ->values()
            ->map(fn (Library $library, int $index): array => self::library($library, $index))
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private static function library(Library $library, int $index): array
    {
        $categories = Category::query()
            ->where('library_id', $library->id)
            ->whereNull('parent_id')
            ->orderBy('name')
            ->get()
            ->map(fn (Category $category, int $categoryIndex): array => self::category($category, $categoryIndex))
            ->filter(fn (array $category): bool => $category['books'] !== [])
            ->values()
            ->all();

        $totalBooks = Book::query()->where('library_id', $library->id)->count();

        return [
            'slug' => $library->slug,
            'name' => $library->name,
            'city' => $library->place?->name ?? '',
            'address' => $library->address,
            'work_time' => $library->work_time ?? '',
            'count' => number_format($totalBooks, 0, ',', '.').' naslova',
            'mark' => self::mark($library->name),
            'color' => self::COLORS[$index % count(self::COLORS)],
            'description' => 'Biblioteka u '.($library->place?->name ?? '').' sa '.$totalBooks.' naslova u fondu.',
            'categories' => $categories,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function category(Category $category, int $index): array
    {
        $books = Book::query()
            ->where('category_primary_id', $category->id)
            ->with('authors')
            ->withCount([
                'activeCopies as available_count' => fn ($query) => $query
                    ->where('borrowed', false)
                    ->where('rec_error', false)
                    ->whereDoesntHave('writeOffs', fn ($writeOff) => $writeOff->whereNull('cancelled_at')),
            ])
            ->withMin('activeCopies', 'publish_year')
            ->orderBy('name')
            ->get()
            ->map(fn (Book $book): array => self::book($book, $category))
            ->all();

        return [
            'slug' => $category->slug,
            'name' => $category->name,
            'description' => 'Naslovi iz kategorije '.$category->name.'.',
            'color' => self::COLORS[$index % count(self::COLORS)],
            'books' => $books,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function book(Book $book, Category $category): array
    {
        return [
            'slug' => $book->slug,
            'title' => $book->name,
            'author' => $book->authors->map(fn ($author): string => $author->displayName())->join(', ') ?: '—',
            'year' => (string) ($book->active_copies_min_publish_year ?? ''),
            'availability' => ($book->available_count ?? 0) > 0 ? 'Dostupno' : 'Nije dostupno',
            'description' => $book->description ?? 'Opis nije dostupan.',
        ];
    }

    private static function mark(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if (count($parts) >= 2) {
            return mb_strtoupper(mb_substr($parts[0], 0, 1).mb_substr($parts[1], 0, 1));
        }

        return mb_strtoupper(mb_substr($name, 0, 2));
    }
}
