@extends('layouts.public')

@section('title', $category['name'].' - '.$library['name'].' | eBiblioteka')

@section('content')
<div class="public-shell catalog-shell" id="top">
    @include('public.partials.nav')
    <main>
        <section class="public-wrap listing-hero"><div class="breadcrumbs"><a href="{{ route('home') }}">Početna</a><span>/</span><a href="{{ route('libraries.index') }}">Biblioteke</a><span>/</span><a href="{{ route('libraries.show', $library['slug']) }}">{{ $library['name'] }}</a><span>/</span><strong>{{ $category['name'] }}</strong></div><div class="listing-title"><span class="category-icon" style="background: {{ $category['color'] }}">✦</span><div><span class="section-label">KATEGORIJA U BIBLIOTECI</span><h1 class="display">{{ $category['name'] }}</h1><p>{{ $category['description'] }}</p></div></div></section>
        <section class="public-wrap book-list-section"><div class="results-heading"><div><span class="section-label">{{ count($category['books']) }} {{ count($category['books']) === 1 ? 'NASLOV' : 'NASLOVA' }}</span><h2 class="display">Knjige u ovoj<br><span class="serif">kategoriji.</span></h2></div><p>Otvorite naslov za više informacija o knjizi i dostupnosti.</p></div><div class="book-list">@foreach ($category['books'] as $index => $book)<a class="book-list-item" href="{{ route('libraries.book', [$library['slug'], $category['slug'], $book['slug']]) }}"><span class="book-list-number">0{{ $index + 1 }}</span><span class="mini-cover" style="background: {{ $category['color'] }}"><b>eB</b><i>{{ $category['name'] }}</i></span><span class="book-list-copy"><strong>{{ $book['title'] }}</strong><small>{{ $book['author'] }} · {{ $book['year'] }}</small><span>{{ \Illuminate\Support\Str::limit($book['description'], 105) }}</span></span><span class="availability">{{ $book['availability'] }}</span><span class="book-list-arrow">↗</span></a>@endforeach</div></section>
        <section class="public-wrap back-strip"><a class="text-link" href="{{ route('libraries.show', $library['slug']) }}">← Sve kategorije</a><span>{{ $library['name'] }}</span></section>
    </main>
    @include('public.partials.footer')
</div>
@endsection
