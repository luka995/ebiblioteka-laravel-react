<?php

namespace App\Http\Controllers;

use App\Models\News;
use Illuminate\View\View;

class PublicNewsController extends Controller
{
    public function index(): View
    {
        $news = News::query()
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->get();

        return view('public.news.index', compact('news'));
    }

    public function show(News $news): View
    {
        $other = News::query()
            ->whereKeyNot($news->id)
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->limit(3)
            ->get();

        return view('public.news.show', compact('news', 'other'));
    }
}
