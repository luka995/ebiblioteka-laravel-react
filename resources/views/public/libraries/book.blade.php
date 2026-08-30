@extends('layouts.public')

@section('title', $book['title'].' | eBiblioteka')

@section('content')
<div class="public-shell catalog-shell" id="top">
    @include('public.partials.nav')
    <main>
        <section class="public-wrap book-detail-section"><div class="breadcrumbs"><a href="{{ route('home') }}">Početna</a><span>/</span><a href="{{ route('libraries.index') }}">Biblioteke</a><span>/</span><a href="{{ route('libraries.show', $library['slug']) }}">{{ $library['name'] }}</a><span>/</span><a href="{{ route('libraries.category', [$library['slug'], $category['slug']]) }}">{{ $category['name'] }}</a><span>/</span><strong>{{ $book['title'] }}</strong></div><div class="book-detail-grid"><div class="large-book-cover" style="background: {{ $category['color'] }}"><span>eB</span><small>{{ $category['name'] }}</small><strong>{{ $book['title'] }}</strong><i>eBiblioteka</i></div><div class="book-detail-copy"><span class="section-label">DETALJ KNJIGE</span><h1 class="display">{{ $book['title'] }}</h1><p class="book-author">{{ $book['author'] }}</p><p class="book-description">{{ $book['description'] }}</p><dl class="book-facts"><div><dt>BIBLIOTEKA</dt><dd>{{ $library['name'] }}</dd></div><div><dt>GODINA</dt><dd>{{ $book['year'] }}</dd></div><div><dt>DOSTUPNOST</dt><dd class="book-available">{{ $book['availability'] }}</dd></div></dl><a class="button button-blue" href="http://localhost:3001">Prijavite se za rezervaciju <span>↗</span></a><small class="reservation-note">Rezervacija će biti dostupna nakon prijave na nalog.</small></div></div></section>
        <section class="public-wrap back-strip"><a class="text-link" href="{{ route('libraries.category', [$library['slug'], $category['slug']]) }}">← Nazad na {{ $category['name'] }}</a><span>{{ $library['name'] }}</span></section>
    </main>
    @include('public.partials.footer')
</div>
@endsection
