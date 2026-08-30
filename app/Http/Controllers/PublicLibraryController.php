<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class PublicLibraryController extends Controller
{
    public function index(Request $request): View
    {
        $query = trim((string) $request->query('q', ''));
        $libraries = collect($this->libraries())
            ->filter(fn (array $library): bool => $query === '' || str_contains($this->searchable($library), mb_strtolower($query)))
            ->values();

        return view('public.libraries.index', compact('libraries', 'query'));
    }

    public function show(string $library): View
    {
        $library = $this->findLibrary($library);

        return view('public.libraries.show', compact('library'));
    }

    public function category(string $library, string $category): View
    {
        $library = $this->findLibrary($library);
        $categoryData = collect($library['categories'])->firstWhere('slug', $category);

        abort_if($categoryData === null, 404);

        return view('public.libraries.category', [
            'library' => $library,
            'category' => $categoryData,
        ]);
    }

    public function book(string $library, string $category, string $book): View
    {
        $library = $this->findLibrary($library);
        $categoryData = collect($library['categories'])->firstWhere('slug', $category);
        $bookData = $categoryData === null ? null : collect($categoryData['books'])->firstWhere('slug', $book);

        abort_if($categoryData === null || $bookData === null, 404);

        return view('public.libraries.book', [
            'library' => $library,
            'category' => $categoryData,
            'book' => $bookData,
        ]);
    }

    private function findLibrary(string $slug): array
    {
        $library = collect($this->libraries())->firstWhere('slug', $slug);

        abort_if($library === null, 404);

        return $library;
    }

    private function searchable(array $library): string
    {
        return mb_strtolower(implode(' ', [$library['name'], $library['city'], $library['address']]));
    }

    private function libraries(): array
    {
        return [
            [
                'slug' => 'biblioteka-svetlost',
                'name' => 'Biblioteka Svetlost',
                'city' => 'Novi Sad',
                'address' => 'Bulevar znanja 12',
                'work_time' => 'Pon - Pet, 08:00 - 19:00',
                'count' => '12.480 naslova',
                'mark' => 'BS',
                'color' => '#ffd968',
                'description' => 'Mesto gde radoznalost ima svoju adresu. Istražite fond za najmlađe, školske dane i sve velike priče između.',
                'categories' => [
                    [
                        'slug' => 'decje-knjige',
                        'name' => 'Dečje knjige',
                        'description' => 'Priče za prve čitalačke pustolovine.',
                        'color' => '#ffd968',
                        'books' => [
                            ['slug' => 'tajna-plavog-kofera', 'title' => 'Tajna plavog kofera', 'author' => 'Jelena Marković', 'year' => '2024', 'availability' => 'Dostupno', 'description' => 'Mila pronalazi stari kofer na tavanu i kreće u potragu za pričom koju je njena baka ostavila između stranica jedne knjige.'],
                            ['slug' => 'zvezda-na-prozoru', 'title' => 'Zvezda na prozoru', 'author' => 'Nikola Ilić', 'year' => '2023', 'availability' => 'Dostupno', 'description' => 'Topla priča o prijateljstvu, malim hrabrostima i jednoj zvezdi koja svake večeri svetli samo za njih.'],
                        ],
                    ],
                    [
                        'slug' => 'lektira',
                        'name' => 'Lektira',
                        'description' => 'Naslovi koji prate školske dane.',
                        'color' => '#83d2eb',
                        'books' => [
                            ['slug' => 'grad-od-papira', 'title' => 'Grad od papira', 'author' => 'Mira Jovanović', 'year' => '2025', 'availability' => 'Dostupno', 'description' => 'Roman o gradu koji se menja svaki put kada neko otvori knjigu i o ljudima koji uče da ga čitaju zajedno.'],
                            ['slug' => 'prica-o-plavoj-reci', 'title' => 'Priča o plavoj reci', 'author' => 'Dušan Petrović', 'year' => '2022', 'availability' => 'Uskoro dostupno', 'description' => 'Putovanje kroz zavičaj, uspomene i pitanja koja sazrevaju zajedno sa svojim čitaocem.'],
                        ],
                    ],
                    [
                        'slug' => 'popularna-nauka',
                        'name' => 'Popularna nauka',
                        'description' => 'Svet oko nas, objašnjen jednostavno.',
                        'color' => '#dcd5f3',
                        'books' => [
                            ['slug' => 'mala-skola-zvezda', 'title' => 'Mala škola zvezda', 'author' => 'Sofija Ristić', 'year' => '2026', 'availability' => 'Dostupno', 'description' => 'Od prve planete do najdalje galaksije, ovo je poziv da nebo posmatramo pažljivije.'],
                        ],
                    ],
                ],
            ],
            [
                'slug' => 'citaonica-dunav',
                'name' => 'Čitaonica Dunav',
                'city' => 'Sombor',
                'address' => 'Ulica lipa 7',
                'work_time' => 'Pon - Sub, 09:00 - 20:00',
                'count' => '8.920 naslova',
                'mark' => 'ČD',
                'color' => '#83d2eb',
                'description' => 'Mirna čitaonica za velike ideje, školske projekte i popodneva koja imaju dovoljno vremena za još jedno poglavlje.',
                'categories' => [
                    [
                        'slug' => 'romani',
                        'name' => 'Romani',
                        'description' => 'Savremene priče i klasici za duge dane.',
                        'color' => '#83d2eb',
                        'books' => [
                            ['slug' => 'zeleni-krovovi', 'title' => 'Zeleni krovovi', 'author' => 'Tara Nikolić', 'year' => '2025', 'availability' => 'Dostupno', 'description' => 'Četvoro prijatelja otkriva da se najvažnije tajne njihovog grada kriju iznad ulica kojima svakog dana prolaze.'],
                            ['slug' => 'vreme-za-price', 'title' => 'Vreme za priče', 'author' => 'Ognjen Kovač', 'year' => '2021', 'availability' => 'Dostupno', 'description' => 'Knjiga o ljudima koji su shvatili da se pažnja, kao i dobra priča, najbolje poklanja bez žurbe.'],
                        ],
                    ],
                    [
                        'slug' => 'poezija',
                        'name' => 'Poezija',
                        'description' => 'Stihovi za tišinu, razgovor i maštu.',
                        'color' => '#dcd5f3',
                        'books' => [
                            ['slug' => 'vrt-koji-pamti', 'title' => 'Vrt koji pamti', 'author' => 'Ana Vuković', 'year' => '2024', 'availability' => 'Dostupno', 'description' => 'Zbirka stihova o odrastanju, dvorištima koja se menjaju i stvarima koje ostaju u nama.'],
                        ],
                    ],
                ],
            ],
            [
                'slug' => 'gradska-biblioteka',
                'name' => 'Gradska biblioteka',
                'city' => 'Kragujevac',
                'address' => 'Trg čitalaca 1',
                'work_time' => 'Pon - Pet, 07:30 - 20:00',
                'count' => '18.240 naslova',
                'mark' => 'GB',
                'color' => '#dcd5f3',
                'description' => 'Gradska biblioteka za učenike, porodice i sve koji veruju da dobra knjiga može da promeni tok dana.',
                'categories' => [
                    [
                        'slug' => 'istorija',
                        'name' => 'Istorija',
                        'description' => 'Prošlost koja pomaže da bolje razumemo sadašnjost.',
                        'color' => '#ffd968',
                        'books' => [
                            ['slug' => 'tragovi-vremena', 'title' => 'Tragovi vremena', 'author' => 'Milan Savić', 'year' => '2023', 'availability' => 'Dostupno', 'description' => 'Kratke i živopisne priče o ljudima, mestima i predmetima koji su oblikovali naš grad.'],
                        ],
                    ],
                    [
                        'slug' => 'umetnost',
                        'name' => 'Umetnost',
                        'description' => 'Boje, oblici i ideje koje ostaju.',
                        'color' => '#f68b72',
                        'books' => [
                            ['slug' => 'slike-koje-govore', 'title' => 'Slike koje govore', 'author' => 'Marija Đorđević', 'year' => '2025', 'availability' => 'Dostupno', 'description' => 'Uvod u umetnost kroz četrdeset dela koja pokazuju kako slika može da postavi bolje pitanje od odgovora.'],
                        ],
                    ],
                ],
            ],
        ];
    }
}
