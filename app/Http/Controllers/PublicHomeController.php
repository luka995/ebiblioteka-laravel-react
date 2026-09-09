<?php

namespace App\Http\Controllers;

use App\Models\News;
use App\Support\LibraryCatalog;
use Illuminate\View\View;

class PublicHomeController extends Controller
{
    public function __invoke(): View
    {
        return view('public.home', [
            'libraries' => LibraryCatalog::all(),
            'recommendations' => [
                ['title' => 'Grad od papira', 'author' => 'Mira Jovanović', 'type' => 'ROMAN', 'color' => 'cover-1'],
                ['title' => 'Tišina između redova', 'author' => 'Luka Petrović', 'type' => 'ESEJ', 'color' => 'cover-2'],
                ['title' => 'Vrt koji pamti', 'author' => 'Ana Vuković', 'type' => 'POEZIJA', 'color' => 'cover-3'],
            ],
            'news' => News::query()->orderByDesc('date')->orderByDesc('id')->limit(3)->get(),
        ]);
    }
}
