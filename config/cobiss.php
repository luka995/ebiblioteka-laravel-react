<?php

return [

    /*
    |--------------------------------------------------------------------------
    | COBISS+ legacy harvest
    |--------------------------------------------------------------------------
    |
    | Podesavanja za jednokratni uvoz realnih bibliografskih zapisa iz COBISS+
    | legacy pretrage. Harvest se izvrsava rucno (`php artisan cobiss:harvest`)
    | i rezultat se cuva u lokalni JSON fixture koji `CobissBookSeeder` cita bez
    | mreze. Time se postuje Crawl-delay i izbegava ponovljeno opterecivanje.
    |
    */

    'fixture_path' => database_path(
        (string) env('COBISS_FIXTURE_PATH', 'seeders/data/cobiss_books.json')
    ),

    // Broj jedinstvenih zapisa koje harvest treba da prikupi.
    'target' => (int) env('COBISS_HARVEST_TARGET', 750),

    // Materijalna vrsta (monografije) i baza u COBISS+.
    'database' => env('COBISS_DATABASE', 'cobib'),
    'material' => env('COBISS_MATERIAL', 'books'),

    // Broj rezultata po strani pretrage (COBISS fiksira na 10).
    'page_size' => 10,

    // Uvazavanje robots.txt Crawl-delay 1 izmedju zahteva ka COBISS-u.
    'crawl_delay' => (float) env('COBISS_CRAWL_DELAY', 1),

    // Prihvataju se samo zapisi na srpskom jeziku.
    'languages' => ['српски'],

    /*
    | Upiti za harvest. Kombinacija zanrova, lektire i domacih izdavaca daje
    | raznovrsne naslove i izdavace.
    */
    'queries' => [
        'лектира',
        'роман',
        'приповетка',
        'поезија',
        'бајке',
        'драма',
        'енциклопедија',
        'историја',
        'географија',
        'Лагуна',
        'Просвета',
        'Вулкан издаваштво',
        'Креативни центар',
        'Змај',
        'Нолит',
        'Дерета',
        'Српска књижевна задруга',
        'Klett',
        'Фреска',
        'Пчелица',
    ],

    /*
    |--------------------------------------------------------------------------
    | Seeder
    |--------------------------------------------------------------------------
    */

    // Ciljne biblioteke: ID-evi ili nazivi razdvojeni zapetom. Prazno = sve aktivne.
    'libraries' => env('COBISS_SEED_LIBRARIES'),

    // Fiksiran seed za deterministicku raspodelu naslova po bibliotekama.
    'seed' => (int) env('COBISS_SEED', 20260915),

    // Broj naslova po biblioteci (minimum koji seeder mora da obezbedi).
    'per_library' => (int) env('COBISS_PER_LIBRARY', 200),

    // Broj kopija po naslovu (ukljucivo).
    'copies_min' => (int) env('COBISS_COPIES_MIN', 1),
    'copies_max' => (int) env('COBISS_COPIES_MAX', 5),

];
