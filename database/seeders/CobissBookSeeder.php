<?php

namespace Database\Seeders;

use App\Models\Author;
use App\Models\Book;
use App\Models\BookCopy;
use App\Models\Library;
use App\Services\Catalog\CobissCategoryMapper;
use App\Services\InventoryNumberService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Popunjava biblioteke realnim bibliografskim zapisima iz COBISS+ kataloga.
 *
 * Podaci se citaju iz lokalnog JSON fixture-a koji priprema `cobiss:harvest`
 * komanda, tako da seeder ne zahteva mrezu. Seeder je idempotentan: naslovi
 * koji vec postoje u biblioteci (po ISBN-u ili nazivu) se preskacu, pa se moze
 * bezbedno pokretati vise puta.
 *
 * Ciljne biblioteke se biraju preko `COBISS_SEED_LIBRARIES` (ID-evi ili nazivi
 * razdvojeni zapetom); ako nije postavljeno, koriste se sve aktivne biblioteke.
 */
class CobissBookSeeder extends Seeder
{
    public function __construct(
        private readonly CobissCategoryMapper $categories,
        private readonly InventoryNumberService $numbers,
    ) {}

    public function run(): void
    {
        $records = $this->loadRecords();

        if ($records === []) {
            throw new RuntimeException(
                'COBISS fixture je prazan ili ne postoji. Pokreni `php artisan cobiss:harvest` prvo.'
            );
        }

        $libraries = $this->targetLibraries();

        if ($libraries->isEmpty()) {
            $this->line('Nema ciljnih biblioteka; seeder nije nista uradio.');

            return;
        }

        $perLibrary = max(1, (int) config('cobiss.per_library', 200));
        $needed = $libraries->count() * $perLibrary;

        if (count($records) < $needed) {
            $this->line(sprintf(
                'UPOZORENJE: fixture ima %d zapisa, a potrebno je %d za %d biblioteka; zapisi se ponavljaju.',
                count($records),
                $needed,
                $libraries->count(),
            ));
        }

        mt_srand((int) config('cobiss.seed', 1));
        shuffle($records);

        $offset = 0;

        foreach ($libraries as $library) {
            $slice = [];

            for ($index = 0; $index < $perLibrary; $index++) {
                $slice[] = $records[($offset + $index) % count($records)];
            }

            $offset += $perLibrary;
            $this->seedLibrary($library, $slice);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $records
     */
    private function seedLibrary(Library $library, array $records): void
    {
        // Barkod je globalno jedinstven (izveden iz inventarnog broja), a
        // inventarni broj je per-library. Da se test-podaci razlicitih
        // biblioteka ne sudare, sekvenca se primuje na opseg vezan za ID.
        $this->numbers->recordManual($library, (string) ($library->id * 1_000_000));

        $existingNames = array_flip(
            Book::query()->where('library_id', $library->id)->pluck('name')->all()
        );

        $existingIsbn = array_flip(
            BookCopy::query()
                ->where('library_id', $library->id)
                ->whereNotNull('isbn')
                ->pluck('isbn')
                ->all()
        );

        $createdBooks = 0;
        $createdCopies = 0;
        $skipped = 0;

        $copiesMin = max(1, (int) config('cobiss.copies_min', 1));
        $copiesMax = max($copiesMin, (int) config('cobiss.copies_max', 3));

        foreach ($records as $record) {
            $title = trim((string) ($record['title'] ?? ''));
            $isbn = isset($record['isbn']) ? trim((string) $record['isbn']) : '';
            $isbn = $isbn === '' ? null : $isbn;

            if ($title === '') {
                continue;
            }

            if (($isbn !== null && isset($existingIsbn[$isbn])) || isset($existingNames[$title])) {
                $skipped++;

                continue;
            }

            $copies = $this->createBook($library, $record, $isbn, random_int($copiesMin, $copiesMax));

            $createdBooks++;
            $createdCopies += $copies;
            $existingNames[$title] = true;

            if ($isbn !== null) {
                $existingIsbn[$isbn] = true;
            }
        }

        $this->line(sprintf(
            '%s: +%d naslova, +%d kopija, preskoceno %d.',
            $library->name,
            $createdBooks,
            $createdCopies,
            $skipped,
        ));

        Log::info('COBISS seeder library done', [
            'library_id' => $library->id,
            'created_books' => $createdBooks,
            'created_copies' => $createdCopies,
            'skipped' => $skipped,
        ]);
    }

    /**
     * @param  array<string, mixed>  $record
     */
    private function createBook(Library $library, array $record, ?string $isbn, int $copies): int
    {
        return DB::transaction(function () use ($library, $record, $isbn, $copies): int {
            $book = Book::create([
                'library_id' => $library->id,
                'name' => trim((string) $record['title']),
                'category_primary_id' => $this->categories->categoryId(
                    $library,
                    $record['category'] ?? null,
                    $record['udk'] ?? null,
                ),
                'description' => $record['description'] ?? null,
            ]);

            $authorIds = collect($record['authors'] ?? [])
                ->map(fn ($name): string => trim((string) $name))
                ->filter()
                ->unique()
                ->map(fn (string $name): int => Author::firstOrCreate([
                    'library_id' => $library->id,
                    'name' => $name,
                ])->id)
                ->all();

            if ($authorIds !== []) {
                $book->authors()->sync($authorIds);
            }

            for ($index = 0; $index < $copies; $index++) {
                $orderNumber = $this->numbers->next($library);

                BookCopy::create([
                    'library_id' => $library->id,
                    'book_id' => $book->id,
                    'order_number' => $orderNumber,
                    'barcode' => $this->numbers->barcodeFor($orderNumber),
                    'isbn' => $isbn,
                    'publisher' => $record['publisher'] ?? null,
                    'publish_place' => $record['publish_place'] ?? null,
                    'publish_year' => $record['publish_year'] ?? null,
                    'num_of_pages' => $record['pages'] ?? null,
                    'dimension' => $record['dimensions'] ?? null,
                    'udk' => $record['udk'] ?? null,
                    'price' => random_int(300, 2500),
                    'date_add' => now()->subDays(random_int(0, 3650))->toDateString(),
                    'borrowed' => false,
                    'reserved' => false,
                    'rec_error' => false,
                ]);
            }

            return $copies;
        });
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function loadRecords(): array
    {
        $path = (string) config('cobiss.fixture_path');

        if ($path === '' || ! File::exists($path)) {
            return [];
        }

        $data = json_decode((string) File::get($path), true);

        if (! is_array($data)) {
            return [];
        }

        $records = $data['records'] ?? $data;

        return is_array($records) ? array_values($records) : [];
    }

    /**
     * @return Collection<int, Library>
     */
    private function targetLibraries(): Collection
    {
        $selection = trim((string) config('cobiss.libraries', ''));

        if ($selection === '') {
            return Library::query()
                ->where('deleted', false)
                ->orderBy('id')
                ->get();
        }

        $tokens = array_values(array_filter(array_map('trim', explode(',', $selection))));

        if ($tokens === []) {
            return Library::query()->where('deleted', false)->orderBy('id')->get();
        }

        $ids = array_values(array_filter($tokens, fn (string $token): bool => ctype_digit($token)));
        $names = array_values(array_diff($tokens, $ids));

        return Library::query()
            ->where('deleted', false)
            ->where(function ($query) use ($ids, $names): void {
                if ($ids !== []) {
                    $query->whereIn('id', array_map('intval', $ids));
                }

                foreach ($names as $name) {
                    $query->orWhere('name', $name);
                }
            })
            ->orderBy('id')
            ->get();
    }

    private function line(string $message): void
    {
        if ($this->command !== null) {
            $this->command->getOutput()->writeln($message);
        }
    }
}
