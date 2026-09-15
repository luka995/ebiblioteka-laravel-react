<?php

use App\Models\Book;
use App\Models\BookCopy;
use App\Models\Library;
use Database\Seeders\CobissBookSeeder;
use Illuminate\Support\Facades\File;

function cobissFixtureRecords(): array
{
    return [
        [
            'cobiss_id' => '1',
            'isbn' => '9780306406157',
            'title' => 'На Дрини ћуприја',
            'authors' => ['Андрић, Иво'],
            'publisher' => 'Просвета',
            'publish_place' => 'Београд',
            'publish_year' => '2005',
            'pages' => 320,
            'dimensions' => '21 cm',
            'udk' => '821.163.41-31',
            'category' => 'роман',
            'description' => 'Роман.',
            'language' => 'српски',
        ],
        [
            'cobiss_id' => '2',
            'isbn' => '9788610000009',
            'title' => 'Приповетке',
            'authors' => ['Петровић, Петар'],
            'publisher' => 'Лагуна',
            'publish_place' => 'Београд',
            'publish_year' => '2018',
            'pages' => 250,
            'dimensions' => '20 cm',
            'udk' => '821.163.41-32',
            'category' => 'приповетка',
            'description' => null,
            'language' => 'српски',
        ],
        [
            'cobiss_id' => '3',
            'isbn' => '9788610000016',
            'title' => 'Физика за први разред',
            'authors' => ['Јовановић, Јован'],
            'publisher' => 'Креативни центар',
            'publish_place' => 'Београд',
            'publish_year' => '2019',
            'pages' => 180,
            'dimensions' => '24 cm',
            'udk' => '53',
            'category' => 'приручник',
            'description' => null,
            'language' => 'српски',
        ],
        [
            'cobiss_id' => '4',
            'isbn' => '9780306406164',
            'title' => 'Поезија',
            'authors' => ['Максимовић, Десанка'],
            'publisher' => 'Змај',
            'publish_place' => 'Нови Сад',
            'publish_year' => '2015',
            'pages' => 120,
            'dimensions' => '19 cm',
            'udk' => '821.163.41-1',
            'category' => 'поезија',
            'description' => null,
            'language' => 'српски',
        ],
    ];
}

function writeCobissFixture(array $records): string
{
    $path = sys_get_temp_dir().'/cobiss_seed_'.uniqid().'.json';
    File::put($path, json_encode(['source' => 'cobiss', 'records' => $records], JSON_UNESCAPED_UNICODE));

    return $path;
}

beforeEach(function () {
    $this->fixturePath = writeCobissFixture(cobissFixtureRecords());
});

afterEach(function () {
    if (isset($this->fixturePath) && File::exists($this->fixturePath)) {
        File::delete($this->fixturePath);
    }
});

test('cobiss seeder fills each library with distinct real titles and copies', function () {
    $libraryA = Library::factory()->create(['name' => 'Biblioteka A']);
    $libraryB = Library::factory()->create(['name' => 'Biblioteka B']);

    config([
        'cobiss.fixture_path' => $this->fixturePath,
        'cobiss.per_library' => 2,
        'cobiss.copies_min' => 1,
        'cobiss.copies_max' => 2,
        'cobiss.seed' => 7,
        'cobiss.libraries' => $libraryA->id.','.$libraryB->id,
    ]);

    $this->seed(CobissBookSeeder::class);

    expect(Book::where('library_id', $libraryA->id)->count())->toBe(2)
        ->and(Book::where('library_id', $libraryB->id)->count())->toBe(2)
        ->and(Book::count())->toBe(4);

    // Naslovi se ne preklapaju izmedju biblioteka.
    $titlesA = Book::where('library_id', $libraryA->id)->pluck('name')->all();
    $titlesB = Book::where('library_id', $libraryB->id)->pluck('name')->all();
    expect(array_intersect($titlesA, $titlesB))->toBe([]);

    foreach ([$libraryA, $libraryB] as $library) {
        $copies = BookCopy::where('library_id', $library->id)->get();

        expect($copies->count())->toBeGreaterThanOrEqual(2)
            ->and($copies->count())->toBeLessThanOrEqual(4);

        foreach ($copies as $copy) {
            expect($copy->barcode)->not->toBeNull()
                ->and($copy->isbn)->not->toBeNull()
                ->and($copy->publisher)->not->toBeNull()
                ->and($copy->order_number)->not->toBeNull();
        }

        // Knjige imaju autora i bar jedna kategoriju (roman/povest/prirucnik).
        $books = Book::where('library_id', $library->id)->withCount('authors')->get();
        expect($books->every(fn (Book $book): bool => $book->authors_count > 0))->toBeTrue();
        expect($books->pluck('category_primary_id')->filter()->isNotEmpty())->toBeTrue();
    }
});

test('cobiss seeder is idempotent', function () {
    $library = Library::factory()->create(['name' => 'Biblioteka Idempotent']);

    config([
        'cobiss.fixture_path' => $this->fixturePath,
        'cobiss.per_library' => 4,
        'cobiss.copies_min' => 1,
        'cobiss.copies_max' => 1,
        'cobiss.seed' => 3,
        'cobiss.libraries' => (string) $library->id,
    ]);

    $this->seed(CobissBookSeeder::class);

    $books = Book::where('library_id', $library->id)->count();
    $copies = BookCopy::where('library_id', $library->id)->count();

    $this->seed(CobissBookSeeder::class);

    expect(Book::where('library_id', $library->id)->count())->toBe($books)
        ->and(BookCopy::where('library_id', $library->id)->count())->toBe($copies)
        ->and($copies)->toBe(4);
});

test('cobiss seeder throws when fixture is missing', function () {
    config(['cobiss.fixture_path' => sys_get_temp_dir().'/nema_ovog_fajla_'.uniqid().'.json']);

    expect(fn () => $this->seed(CobissBookSeeder::class))->toThrow(RuntimeException::class);
});
