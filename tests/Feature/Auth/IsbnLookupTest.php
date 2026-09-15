<?php

use App\Models\Author;
use App\Models\Book;
use App\Models\BookCopy;
use App\Models\Library;
use App\Models\User;
use App\Services\Isbn\CategoryTranslator;
use App\Services\Isbn\GoogleBooksProvider;
use App\Services\Isbn\IsbnMetadataService;
use App\Services\Isbn\NbsCatalogProvider;
use App\Services\Isbn\OpenLibraryProvider;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

function isbnLookupHeaders(array $extra = []): array
{
    return array_merge([
        'Origin' => 'http://localhost:3001',
        'Accept' => 'application/json',
    ], $extra);
}

function isbnLibrary(): Library
{
    return Library::factory()->create();
}

function openLibraryResponse(string $isbn, array $overrides = []): array
{
    return [
        "ISBN:{$isbn}" => array_merge([
            'title' => 'Na Drini ćuprija',
            'authors' => [['name' => 'Ivo Andrić']],
            'publishers' => [['name' => 'Prosveta']],
            'publish_places' => [['name' => 'Beograd']],
            'publish_date' => '2005',
            'number_of_pages' => 320,
        ], $overrides),
    ];
}

function googleBooksResponse(string $title = 'Google knjiga'): array
{
    return [
        'items' => [[
            'volumeInfo' => [
                'title' => $title,
                'authors' => ['Petar Petrović'],
                'publisher' => 'Laguna',
                'publishedDate' => '2010-05-01',
                'pageCount' => 150,
                'description' => 'Opis.',
                'imageLinks' => ['thumbnail' => 'http://books.google.com/cover.jpg'],
            ],
        ]],
    ];
}

function cobissSearchResponse(string $href = 'bib/272251148'): string
{
    return '<html><body><div id="search-results-container" data-hits="1"><table><tbody>'
        .'<tr class="odd biblioentry" data-href="'.$href.'" data-cobiss-id="272251148">'
        .'<td><a href="'.$href.'" class="title value">Приповетке : (1961-1975)</a>'
        .'<span class="author value">Андрић, Иво, 1892-1975 = Andrić, Ivo, 1892-1975</span>'
        .'<span class="year field-data publishDate-data"><span class="publishDate-data value">2018</span></span>'
        .'</td></tr></tbody></table></div></body></html>';
}

function cobissFullResponse(string $isbn = '978-86-81131-21-3 (пласт.)'): array
{
    return [
        'titleCard' => ['name' => 'Наслов', 'value' => 'Приповетке : (1961-1975) / Иво Андрић ; приредила Милица Ћуковић'],
        'author700701' => ['name' => 'Аутор - особа', 'value' => 'Андрић, Иво, 1892-1975 = Andrić, Ivo, 1892-1975'],
        'publisherCard' => ['name' => 'Издавање и производња', 'value' => 'Београд : Задужбина Иве Андрића, 2018 (Нови Сад : Сајнос)'],
        'publishDate' => ['name' => 'Година', 'value' => '2018'],
        'ph_descriptionCard' => ['name' => 'Физички опис', 'value' => '574 стр. : илустр. ; 24 cm'],
        'materialDescr' => ['name' => 'Врста грађе', 'value' => 'кратка проза'],
        'udkCard' => ['name' => 'УДК', 'value' => '821.163.41-32 <br> 821.163.41-32.09 Andrić I.'],
        'isbnCard' => ['name' => 'ISBN', 'value' => $isbn],
        'notesCard' => ['name' => 'Напомене', 'value' => 'Ауторова слика <br> Тираж 500'],
    ];
}

function enableCobiss(): void
{
    config([
        'isbn.nbs.enabled' => true,
        'isbn.nbs.search_url' => 'https://cobiss.test/cobiss/sr/sr/bib/search',
        'isbn.nbs.base_url' => 'https://cobiss.test/cobiss/sr/sr/',
    ]);
}

function fakeCobiss(string $searchHtml, array $fullJson): void
{
    Http::fake(function (Request $request) use ($searchHtml, $fullJson) {
        if (str_contains($request->url(), '/bib/search')) {
            return Http::response($searchHtml);
        }

        if (str_contains($request->url(), '/full')) {
            return Http::response($fullJson, 200, ['Content-Type' => 'application/json']);
        }

        return Http::response('', 404);
    });
}

test('isbn lookup returns internal database data without calling external apis', function () {
    Http::preventStrayRequests();

    $admin = User::factory()->superAdmin()->create();
    $library = isbnLibrary();
    $book = Book::factory()->for($library)->create(['name' => 'Interna knjiga']);
    BookCopy::factory()->for($library)->for($book)->create([
        'order_number' => '1',
        'isbn' => '9780306406157',
    ]);

    $this->actingAs($admin)->getJson(
        '/api/v1/books/isbn-lookup?isbn=9780306406157&library_id='.$library->id,
        isbnLookupHeaders()
    )
        ->assertOk()
        ->assertJsonPath('source', 'database')
        ->assertJsonPath('book.name', 'Interna knjiga')
        ->assertJsonCount(1, 'existing_copies');
});

test('isbn lookup maps open library metadata', function () {
    $admin = User::factory()->superAdmin()->create();
    $library = isbnLibrary();

    Http::fake(['openlibrary.org/*' => Http::response(openLibraryResponse('9780306406157'))]);

    $this->actingAs($admin)->getJson(
        '/api/v1/books/isbn-lookup?isbn=978-0-306-40615-7&library_id='.$library->id,
        isbnLookupHeaders()
    )
        ->assertOk()
        ->assertJsonPath('source', 'open_library')
        ->assertJsonPath('metadata.title', 'Na Drini ćuprija')
        ->assertJsonPath('metadata.authors.0', 'Ivo Andrić')
        ->assertJsonPath('metadata.publisher', 'Prosveta')
        ->assertJsonPath('metadata.publish_place', 'Beograd')
        ->assertJsonPath('metadata.publish_year', '2005')
        ->assertJsonPath('metadata.pages', 320);
});

test('isbn lookup falls back to google books when open library is empty', function () {
    $admin = User::factory()->superAdmin()->create();
    $library = isbnLibrary();

    Http::fake([
        'openlibrary.org/*' => Http::response([]),
        'googleapis.com/*' => Http::response(googleBooksResponse()),
    ]);

    $this->actingAs($admin)->getJson(
        '/api/v1/books/isbn-lookup?isbn=9780306406157&library_id='.$library->id,
        isbnLookupHeaders()
    )
        ->assertOk()
        ->assertJsonPath('source', 'google_books')
        ->assertJsonPath('metadata.title', 'Google knjiga')
        ->assertJsonPath('metadata.cover_url', 'https://books.google.com/cover.jpg');
});

test('isbn lookup falls through providers when one fails', function () {
    $admin = User::factory()->superAdmin()->create();
    $library = isbnLibrary();

    Http::fake([
        'openlibrary.org/*' => fn () => throw new ConnectionException('timeout'),
        'googleapis.com/*' => Http::response(googleBooksResponse()),
    ]);

    $this->actingAs($admin)->getJson(
        '/api/v1/books/isbn-lookup?isbn=9780306406157&library_id='.$library->id,
        isbnLookupHeaders()
    )
        ->assertOk()
        ->assertJsonPath('source', 'google_books');
});

test('isbn lookup returns null source when nothing is found', function () {
    $admin = User::factory()->superAdmin()->create();
    $library = isbnLibrary();

    Http::fake([
        'openlibrary.org/*' => Http::response([]),
        'googleapis.com/*' => Http::response(['totalItems' => 0]),
    ]);

    $this->actingAs($admin)->getJson(
        '/api/v1/books/isbn-lookup?isbn=9780306406157&library_id='.$library->id,
        isbnLookupHeaders()
    )
        ->assertOk()
        ->assertJsonPath('source', null)
        ->assertJsonPath('metadata', null);
});

test('isbn lookup caches provider results', function () {
    $admin = User::factory()->superAdmin()->create();
    $library = isbnLibrary();

    Http::fake(['openlibrary.org/*' => Http::response(openLibraryResponse('9780306406157'))]);

    $url = '/api/v1/books/isbn-lookup?isbn=9780306406157&library_id='.$library->id;

    $this->actingAs($admin)->getJson($url, isbnLookupHeaders())->assertOk();
    $this->actingAs($admin)->getJson($url, isbnLookupHeaders())->assertOk();

    Http::assertSentCount(1);
});

test('isbn lookup rejects an invalid isbn', function () {
    $admin = User::factory()->superAdmin()->create();
    $library = isbnLibrary();

    $this->actingAs($admin)->getJson(
        '/api/v1/books/isbn-lookup?isbn=not-an-isbn&library_id='.$library->id,
        isbnLookupHeaders()
    )->assertStatus(422);
});

test('external metadata is matched against existing titles', function () {
    $admin = User::factory()->superAdmin()->create();
    $library = isbnLibrary();
    $book = Book::factory()->for($library)->create(['name' => 'Na Drini ćuprija']);
    $author = Author::factory()->for($library)->create(['name' => 'Ivo Andrić']);
    $book->authors()->attach($author->id);

    Http::fake(['openlibrary.org/*' => Http::response(openLibraryResponse('9780306406157'))]);

    $this->actingAs($admin)->getJson(
        '/api/v1/books/isbn-lookup?isbn=9780306406157&library_id='.$library->id,
        isbnLookupHeaders()
    )
        ->assertOk()
        ->assertJsonPath('source', 'open_library')
        ->assertJsonPath('matches.0.id', $book->id);
});

test('nbs provider scrapes cobiss search and full json detail', function () {
    enableCobiss();
    fakeCobiss(cobissSearchResponse(), cobissFullResponse());

    $metadata = app(NbsCatalogProvider::class)->lookup('978-86-81131-21-3');

    expect($metadata)->not->toBeNull()
        ->and($metadata->source)->toBe('nbs')
        ->and($metadata->isbn)->toBe('9788681131213')
        ->and($metadata->title)->toBe('Приповетке : (1961-1975)')
        ->and($metadata->authors)->toBe(['Андрић, Иво'])
        ->and($metadata->publisher)->toBe('Задужбина Иве Андрића')
        ->and($metadata->publishPlace)->toBe('Београд')
        ->and($metadata->publishYear)->toBe('2018')
        ->and($metadata->pages)->toBe(574)
        ->and($metadata->dimensions)->toBe('24 cm')
        ->and($metadata->category)->toBe('кратка проза')
        ->and($metadata->udk)->toBe('821.163.41-32')
        ->and($metadata->description)->toBe("Ауторова слика\nТираж 500");
});

test('nbs provider ignores records whose isbn does not match', function () {
    enableCobiss();
    fakeCobiss(cobissSearchResponse(), cobissFullResponse('978-0-306-40615-7'));

    expect(app(NbsCatalogProvider::class)->lookup('9788681131213'))->toBeNull();
});

test('nbs provider returns null when search has no hits', function () {
    enableCobiss();
    fakeCobiss('<html><body><div id="search-results-container" data-hits="0"></div></body></html>', cobissFullResponse());

    expect(app(NbsCatalogProvider::class)->lookup('9788681131213'))->toBeNull();
});

test('isbn lookup prefers nbs over open library', function () {
    $admin = User::factory()->superAdmin()->create();
    $library = isbnLibrary();

    enableCobiss();

    Http::fake(function (Request $request) {
        if (str_contains($request->url(), 'openlibrary.org')) {
            return Http::response(openLibraryResponse('9788681131213'));
        }

        if (str_contains($request->url(), '/bib/search')) {
            return Http::response(cobissSearchResponse());
        }

        if (str_contains($request->url(), '/full')) {
            return Http::response(cobissFullResponse(), 200, ['Content-Type' => 'application/json']);
        }

        return Http::response('', 404);
    });

    $this->actingAs($admin)->getJson(
        '/api/v1/books/isbn-lookup?isbn=978-86-81131-21-3&library_id='.$library->id,
        isbnLookupHeaders()
    )
        ->assertOk()
        ->assertJsonPath('source', 'nbs')
        ->assertJsonPath('metadata.title', 'Приповетке : (1961-1975)')
        ->assertJsonPath('metadata.publisher', 'Задужбина Иве Андрића');

    Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'openlibrary.org'));
});

test('isbn lookup falls back to open library when nbs has no hits', function () {
    $admin = User::factory()->superAdmin()->create();
    $library = isbnLibrary();

    enableCobiss();

    Http::fake(function (Request $request) {
        if (str_contains($request->url(), '/bib/search')) {
            return Http::response('<html><body><div id="search-results-container" data-hits="0"></div></body></html>');
        }

        if (str_contains($request->url(), 'openlibrary.org')) {
            return Http::response(openLibraryResponse('9780306406157'));
        }

        return Http::response('', 404);
    });

    $this->actingAs($admin)->getJson(
        '/api/v1/books/isbn-lookup?isbn=9780306406157&library_id='.$library->id,
        isbnLookupHeaders()
    )
        ->assertOk()
        ->assertJsonPath('source', 'open_library');
});

test('negative isbn lookups are not cached', function () {
    $admin = User::factory()->superAdmin()->create();
    $library = isbnLibrary();

    Http::fake([
        'openlibrary.org/*' => Http::response([]),
        'googleapis.com/*' => Http::response(['totalItems' => 0]),
    ]);

    $url = '/api/v1/books/isbn-lookup?isbn=9780306406157&library_id='.$library->id;

    $this->actingAs($admin)->getJson($url, isbnLookupHeaders())->assertOk()->assertJsonPath('source', null);
    $this->actingAs($admin)->getJson($url, isbnLookupHeaders())->assertOk()->assertJsonPath('source', null);

    Http::assertSentCount(4);
});

test('isbn providers send an identifying user agent', function () {
    config(['isbn.user_agent' => 'eBiblioteka/1.0 (+mailto:akademijafilipovic@gmail.com)']);
    Http::fake(['openlibrary.org/*' => Http::response(openLibraryResponse('9780306406157'))]);

    app(OpenLibraryProvider::class)->lookup('9780306406157');

    Http::assertSent(fn (Request $request) => $request->hasHeader(
        'User-Agent',
        'eBiblioteka/1.0 (+mailto:akademijafilipovic@gmail.com)',
    ));
});

test('isbn provider logs a warning when the request fails', function () {
    Log::spy();
    Http::fake(['openlibrary.org/*' => Http::response('server error', 503)]);

    app(OpenLibraryProvider::class)->lookup('9780306406157');

    Log::shouldHaveReceived('warning')->withArgs(function (string $message, array $context): bool {
        return $message === 'ISBN provider failed'
            && $context['provider'] === 'open_library'
            && $context['status'] === 503
            && $context['reason'] === 'http_error';
    })->once();
});

test('isbn provider logs when no metadata is returned', function () {
    Log::spy();
    Http::fake(['openlibrary.org/*' => Http::response([])]);

    app(OpenLibraryProvider::class)->lookup('9780306406157');

    Log::shouldHaveReceived('info')->withArgs(function (string $message, array $context): bool {
        return $message === 'ISBN provider returned no metadata'
            && $context['provider'] === 'open_library'
            && $context['reason'] === 'no_title';
    })->once();
});

test('nbs provider logs when the search has no hits', function () {
    enableCobiss();
    Log::spy();
    fakeCobiss('<html><body><div id="search-results-container" data-hits="0"></div></body></html>', cobissFullResponse());

    app(NbsCatalogProvider::class)->lookup('9788681131213');

    Log::shouldHaveReceived('info')->withArgs(function (string $message, array $context): bool {
        return $message === 'ISBN provider returned no metadata'
            && $context['provider'] === 'nbs'
            && $context['reason'] === 'no_hits';
    })->once();
});

test('isbn lookup translates external category to serbian', function () {
    $this->app->instance(CategoryTranslator::class, new class extends CategoryTranslator
    {
        public function translate(?string $category): ?string
        {
            return $category === null ? null : 'Преведено: '.$category;
        }
    });

    Http::fake(['openlibrary.org/*' => Http::response([
        'ISBN:9780306406157' => [
            'title' => 'A Book',
            'authors' => [['name' => 'Author']],
            'subjects' => [['name' => 'Fiction']],
        ],
    ])]);

    $metadata = app(IsbnMetadataService::class)->lookup('9780306406157');

    expect($metadata)->not->toBeNull()
        ->and($metadata->category)->toBe('Преведено: Fiction');
});

test('google books api key is never written to the log', function () {
    config(['isbn.google_books_key' => 'SECRET-GOOGLE-KEY']);
    Log::spy();
    Http::fake(['googleapis.com/*' => Http::response(['totalItems' => 0])]);

    app(GoogleBooksProvider::class)->lookup('9780306406157');

    Log::shouldHaveReceived('debug')->withArgs(function (string $message, array $context): bool {
        if ($message !== 'ISBN provider request') {
            return false;
        }

        return ($context['has_key'] ?? false) === true
            && ! str_contains((string) json_encode($context), 'SECRET-GOOGLE-KEY');
    })->once();
});
