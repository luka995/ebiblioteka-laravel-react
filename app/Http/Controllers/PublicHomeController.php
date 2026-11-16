<?php

namespace App\Http\Controllers;

use App\Models\News;
use App\Support\LibraryCatalog;
use Illuminate\View\View;

class PublicHomeController extends Controller
{
    public function __invoke(): View
    {
        $libraries = LibraryCatalog::all();

        return view('public.home', [
            'libraries' => $libraries,
            'libraryCount' => count($libraries),
            'news' => News::query()->orderByDesc('date')->orderByDesc('id')->limit(3)->get(),
            'contact' => config('public_site.contact'),
            'comparison' => config('public_site.comparison'),
            'modules' => config('public_site.modules'),
            'demo' => config('public_site.demo'),
            'guide' => config('public_site.guide'),
            'faq' => config('public_site.faq'),
            'schoolTypes' => config('public_site.school_types'),
        ]);
    }
}
