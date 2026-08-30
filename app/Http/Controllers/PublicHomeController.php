<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class PublicHomeController extends Controller
{
    public function __invoke(): View
    {
        return view('public.home', [
            'libraries' => [
                ['slug' => 'biblioteka-svetlost', 'name' => 'Biblioteka Svetlost', 'city' => 'Novi Sad', 'count' => '12.480 naslova', 'mark' => 'BS', 'color' => '#ffd968'],
                ['slug' => 'citaonica-dunav', 'name' => 'Čitaonica Dunav', 'city' => 'Sombor', 'count' => '8.920 naslova', 'mark' => 'ČD', 'color' => '#83d2eb'],
                ['slug' => 'gradska-biblioteka', 'name' => 'Gradska biblioteka', 'city' => 'Kragujevac', 'count' => '18.240 naslova', 'mark' => 'GB', 'color' => '#dcd5f3'],
            ],
            'recommendations' => [
                ['title' => 'Grad od papira', 'author' => 'Mira Jovanović', 'type' => 'ROMAN', 'color' => 'cover-1'],
                ['title' => 'Tišina između redova', 'author' => 'Luka Petrović', 'type' => 'ESEJ', 'color' => 'cover-2'],
                ['title' => 'Vrt koji pamti', 'author' => 'Ana Vuković', 'type' => 'POEZIJA', 'color' => 'cover-3'],
            ],
            'news' => [
                ['date' => '12. jun 2026.', 'category' => 'IZ IZA POLICA', 'title' => 'Kako nastaje dobra preporuka za čitanje?'],
                ['date' => '04. jun 2026.', 'category' => 'PREPORUKE', 'title' => 'Letnji izbor: deset knjiga za spora popodneva'],
                ['date' => '28. maj 2026.', 'category' => 'ZAJEDNICA', 'title' => 'Upoznajte biblioteke koje čuvaju komšijske priče'],
            ],
        ]);
    }
}
