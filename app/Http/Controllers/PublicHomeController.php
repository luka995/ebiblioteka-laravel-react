<?php

namespace App\Http\Controllers;

use App\Support\LibraryCatalog;
use App\Support\PublicTheme;
use Illuminate\View\View;

class PublicHomeController extends Controller
{
    public function __invoke(): View
    {
        return view(PublicTheme::view('public.home'), [
            'libraries' => LibraryCatalog::all(),
            'recommendations' => [
                ['title' => 'Grad od papira', 'author' => 'Mira Jovanović', 'type' => 'ROMAN', 'color' => 'cover-1'],
                ['title' => 'Tišina između redova', 'author' => 'Luka Petrović', 'type' => 'ESEJ', 'color' => 'cover-2'],
                ['title' => 'Vrt koji pamti', 'author' => 'Ana Vuković', 'type' => 'POEZIJA', 'color' => 'cover-3'],
            ],
            'news' => [
                ['date' => '12. jun 2026.', 'category' => 'IZA POLICA', 'title' => 'Kako nastaje dobra preporuka za čitanje?'],
                ['date' => '04. jun 2026.', 'category' => 'PREPORUKE', 'title' => 'Letnji izbor: deset knjiga za spora popodneva'],
                ['date' => '28. maj 2026.', 'category' => 'ZAJEDNICA', 'title' => 'Upoznajte biblioteke koje čuvaju komšijske priče'],
            ],
        ]);
    }
}
