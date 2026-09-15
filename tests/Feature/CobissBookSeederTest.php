<?php

use App\Models\Book;
use App\Models\BookCopy;
use App\Models\Library;
use App\Support\BarCode;
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

function configureCobissSeeder(Library $library, string $fixturePath, int $perLibrary, int $copiesMin, int $copiesMax): void
{
    config([
        'cobiss.fixture_path' => $fixturePath,
        'cobiss.per_library' => $perLibrary,
        'cobiss.copies_min' => $copiesMin,
        'cobiss.copies_max' => $copiesMax,
        'cobiss.seed' => 7,
        'cobiss.libraries' => (string) $library->id,
    ]);
}

beforeEach(function () {
    $this->fixturePath = writeCobissFixture(cobissFixtureRecords());
});

afterEach(function () {
    if (isset($this->fixturePath) && File::exists($this->fixturePath)) {
        File::delete($this->fixturePath);
    }
});

test('cobiss seeder fills a library with real titles, authors and copies', function () {
    $library = Library::factory()->create(['name' => 'Akademija Filipovic']);
    configureCobissSeeder($library, $this->fixturePath, 4, 1, 2);

    $this->seed(CobissBookSeeder::class);

    $books = Book::where('library_id', $library->id)->withCount('authors')->get();

    expect($books)->toHaveCount(4)
        ->and($books->every(fn (Book $book): bool => $book->authors_count > 0))->toBeTrue()
        ->and($books->pluck('category_primary_id')->filter()->isNotEmpty())->toBeTrue();

    $copies = BookCopy::where('library_id', $library->id)->get();

    expect($copies->count())->toBeGreaterThanOrEqual(4)
        ->and($copies->count())->toBeLessThanOrEqual(8);

    foreach ($copies as $copy) {
        expect($copy->barcode)->not->toBeNull()
            ->and(BarCode::validate($copy->barcode))->toBeTrue()
            ->and($copy->isbn)->not->toBeNull()
            ->and($copy->publisher)->not->toBeNull()
            ->and($copy->order_number)->not->toBeNull();
    }
});

test('cobiss seeder generates contiguous inventory numbers without gaps', function () {
    $library = Library::factory()->create();
    configureCobissSeeder($library, $this->fixturePath, 4, 2, 2);

    $this->seed(CobissBookSeeder::class);

    $numbers = BookCopy::where('library_id', $library->id)
        ->orderByRaw('CAST(order_number AS BIGINT)')
        ->pluck('order_number')
        ->map(fn ($value): int => (int) $value)
        ->all();

    expect($numbers)->toBe(range(1, 8))
        ->and($library->inventorySequence()->first()->last_number)->toBe(8);
});

test('cobiss seeder is idempotent', function () {
    $library = Library::factory()->create();
    configureCobissSeeder($library, $this->fixturePath, 4, 1, 1);

    $this->seed(CobissBookSeeder::class);
    $this->seed(CobissBookSeeder::class);

    expect(Book::where('library_id', $library->id)->count())->toBe(4)
        ->and(BookCopy::where('library_id', $library->id)->count())->toBe(4)
        ->and($library->inventorySequence()->first()->last_number)->toBe(4);
});

test('cobiss seeder throws when fixture is missing', function () {
    config(['cobiss.fixture_path' => sys_get_temp_dir().'/nema_ovog_fajla_'.uniqid().'.json']);

    expect(fn () => $this->seed(CobissBookSeeder::class))->toThrow(RuntimeException::class);
});
