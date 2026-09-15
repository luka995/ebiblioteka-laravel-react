<?php

return [

    /*
    |--------------------------------------------------------------------------
    | ISBN metadata lookup
    |--------------------------------------------------------------------------
    |
    | Redosled izvora: NBS/COBISS+ scraping -> Open Library -> Google Books.
    | Kesiraju se samo uspesni rezultati (negativni se ne pamte).
    |
    */

    'timeout' => env('ISBN_HTTP_TIMEOUT', 8),

    'cache_ttl' => env('ISBN_CACHE_TTL', 86400),

    'google_books_key' => env('GOOGLE_BOOKS_API_KEY'),

    'user_agent' => env('ISBN_USER_AGENT', 'eBiblioteka/1.0 (+mailto:akademijafilipovic@gmail.com)'),

    'translate' => [
        'enabled' => env('ISBN_TRANSLATE_ENABLED', true),
        'target' => env('ISBN_TRANSLATE_TARGET', 'sr'),
        'url' => env('ISBN_TRANSLATE_URL'),
        'cache_ttl' => (int) env('ISBN_TRANSLATE_CACHE_TTL', 604800),
    ],

    'nbs' => [
        'enabled' => env('NBS_CATALOG_ENABLED', false),

        // COBISS+ legacy HTML pretraga (uzajamni katalog / Narodna biblioteka Srbije).
        'search_url' => env('NBS_CATALOG_SEARCH_URL', 'https://plus-legacy.cobiss.net/cobiss/sr/sr/bib/search'),
        'query_param' => env('NBS_CATALOG_QUERY_PARAM', 'q'),
        'search_params' => [
            'db' => env('NBS_CATALOG_DATABASE', 'cobib'),
            'mat' => env('NBS_CATALOG_MATERIAL', 'allmaterials'),
        ],

        // Bazni URL za relativne linkove iz rezultata (data-href="bib/{id}").
        'base_url' => env('NBS_CATALOG_BASE_URL', 'https://plus-legacy.cobiss.net/cobiss/sr/sr/'),

        // Detaljni zapis kao JSON: bib/{database}/{id}/full.
        'record_database' => env('NBS_CATALOG_RECORD_DATABASE', 'COBIB'),
        'full_path' => env('NBS_CATALOG_FULL_PATH', 'bib/{database}/{id}/full'),

        // Broj pogodaka koji se detaljno proveravaju i pauza izmedju zahteva (robots: Crawl-delay 1).
        'max_results' => (int) env('NBS_CATALOG_MAX_RESULTS', 3),
        'crawl_delay' => (float) env('NBS_CATALOG_CRAWL_DELAY', 1),

        // XPath izrazi za listu pogodaka; podesivi ako se HTML promeni.
        'xpaths' => [
            'result_row' => "//tr[contains(concat(' ', normalize-space(@class), ' '), ' biblioentry ')]",
            'result_href' => './@data-href',
            'result_title' => ".//a[contains(concat(' ', normalize-space(@class), ' '), ' title ')]",
            'result_author' => ".//span[contains(concat(' ', normalize-space(@class), ' '), ' author ')]",
            'result_year' => ".//span[contains(@class, 'publishDate-data')]",
        ],
    ],

];
