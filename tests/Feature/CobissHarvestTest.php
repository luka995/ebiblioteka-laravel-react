<?php

use App\Services\Catalog\CobissHarvestService;
use App\Services\Isbn\NbsCatalogProvider;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

function harvestSearchHtml(array $ids): string
{
    $rows = '';

    foreach ($ids as $id) {
        $rows .= '<tr class="odd biblioentry" data-href="bib/'.$id.'" data-cobiss-id="'.$id.'">'
            .'<td><a href="bib/'.$id.'" class="title value">Naslov '.$id.'</a>'
            .'<span class="author value">Autor '.$id.'</span>'
            .'<span class="year field-data publishDate-data"><span class="publishDate-data value">2020</span></span>'
            .'</td></tr>';
    }

    return '<html><body><div id="search-results-container" data-hits="'.count($ids).'"><table><tbody>'
        .$rows.'</tbody></table></div></body></html>';
}

function harvestFullJson(string $isbn, string $language = 'српски'): array
{
    return [
        'titleCard' => ['value' => 'Naslov '.$isbn],
        'author700701' => ['value' => 'Петровић, Петар, 1970- = Petrović, Petar, 1970-'],
        'publisherCard' => ['value' => 'Београд : Лагуна, 2020 (Београд : Штампа)'],
        'isbnCard' => ['value' => $isbn.' (broš.)'],
        'publishDate' => ['value' => '2020'],
        'ph_descriptionCard' => ['value' => '250 стр. ; 21 cm'],
        'materialDescr' => ['value' => 'роман'],
        'udkCard' => ['value' => '821.163.41-31'],
        'languageCard' => ['value' => $language],
    ];
}

function enableHarvestCobiss(): void
{
    config([
        'isbn.nbs.enabled' => true,
        'isbn.nbs.crawl_delay' => 0,
        'isbn.nbs.search_url' => 'https://cobiss.test/cobiss/sr/sr/bib/search',
        'isbn.nbs.base_url' => 'https://cobiss.test/cobiss/sr/sr/',
        'cobiss.crawl_delay' => 0,
    ]);
}

function fakeHarvestHttp(array $ids, array $isbns, string $language = 'српски'): void
{
    Http::fake(function (Request $request) use ($ids, $isbns, $language) {
        if (str_contains($request->url(), '/bib/search')) {
            return Http::response(harvestSearchHtml($ids));
        }

        if (str_contains($request->url(), '/full')) {
            preg_match('/(\d+)\/full/', $request->url(), $matches);
            $index = array_search($matches[1] ?? '', $ids, true);
            $isbn = $isbns[$index === false ? 0 : $index];

            return Http::response(harvestFullJson($isbn, $language), 200, ['Content-Type' => 'application/json']);
        }

        return Http::response('', 404);
    });
}

test('nbs provider reads a record by cobiss id', function () {
    enableHarvestCobiss();
    fakeHarvestHttp(['111'], ['9780306406157']);

    $metadata = app(NbsCatalogProvider::class)->lookupRecord('111');

    expect($metadata)->not->toBeNull()
        ->and($metadata->title)->toBe('Naslov 9780306406157')
        ->and($metadata->isbn)->toBe('9780306406157')
        ->and($metadata->language)->toBe('српски')
        ->and($metadata->authors)->toBe(['Петровић, Петар'])
        ->and($metadata->publisher)->toBe('Лагуна');
});

test('nbs provider search parses rows and total hits', function () {
    enableHarvestCobiss();
    fakeHarvestHttp(['111', '222'], ['9780306406157', '9788610000009']);

    $result = app(NbsCatalogProvider::class)->search('лектира');

    expect($result['total'])->toBe(2)
        ->and($result['rows'])->toHaveCount(2)
        ->and($result['rows'][0]['id'])->toBe('111')
        ->and($result['rows'][1]['id'])->toBe('222');
});

test('harvest service accepts only serbian records and deduplicates', function () {
    enableHarvestCobiss();
    // 111 i 333 su na srpskom, 222 na engleskom (odbacuje se).
    Http::fake(function (Request $request) {
        if (str_contains($request->url(), '/bib/search')) {
            return Http::response(harvestSearchHtml(['111', '222', '333']));
        }

        if (str_contains($request->url(), '/full')) {
            $json = str_contains($request->url(), '222')
                ? harvestFullJson('9788610000016', 'енглески')
                : harvestFullJson(
                    str_contains($request->url(), '333') ? '9788610000009' : '9780306406157'
                );

            return Http::response($json, 200, ['Content-Type' => 'application/json']);
        }

        return Http::response('', 404);
    });

    $records = app(CobissHarvestService::class)->harvest(3, ['лектира'], [], null);

    expect($records)->toHaveCount(2);

    foreach ($records as $record) {
        expect($record['language'])->toBe('српски');
    }
});

test('cobiss harvest command writes a fixture file', function () {
    enableHarvestCobiss();
    fakeHarvestHttp(['111', '222'], ['9780306406157', '9788610000009']);

    $path = sys_get_temp_dir().'/cobiss_fixture_'.uniqid().'.json';

    $this->artisan('cobiss:harvest', [
        '--target' => 2,
        '--queries' => 'лектира',
        '--out' => $path,
        '--fresh' => true,
    ])->assertSuccessful();

    expect(File::exists($path))->toBeTrue();

    $data = json_decode((string) File::get($path), true);

    expect($data['count'])->toBe(2)
        ->and($data['records'])->toHaveCount(2)
        ->and($data['source'])->toBe('cobiss');

    File::delete($path);
});
