@extends('layouts.public')

@section('title', $library['name'].' | eBiblioteka')

@section('content')
<div class="public-shell catalog-shell" id="top">
    @include('public.partials.nav')
    <main>
        <section class="public-wrap catalog-detail-hero"><div><div class="breadcrumbs"><a href="{{ route('home') }}">Početna</a><span>/</span><a href="{{ route('libraries.index') }}">Biblioteke</a><span>/</span><strong>{{ $library['name'] }}</strong></div><span class="section-label">DOBRO DOŠLI U BIBLIOTEKU</span><h1 class="display">{{ $library['name'] }}</h1><p>{{ $library['description'] }}</p><div class="library-facts"><span><strong>ADRESA</strong>{{ $library['city'] }} · {{ $library['address'] }}</span><span><strong>RADNO VREME</strong>{{ $library['work_time'] }}</span><span><strong>FOND</strong>{{ $library['count'] }}</span></div></div><div class="detail-emblem" style="background: {{ $library['color'] }}"><span>{{ $library['mark'] }}</span><small>{{ $library['city'] }}</small></div></section>
        <section class="public-wrap category-section"><div class="results-heading"><div><span class="section-label">KATEGORIJE U BIBLIOTECI</span><h2 class="display">Šta želite da<br><span class="serif">čitate danas?</span></h2></div><p>Izaberite kategoriju da pregledate naslove koji su dostupni u ovoj biblioteci.</p></div><div class="category-grid">@foreach ($library['categories'] as $index => $category)<a class="category-card" href="{{ route('libraries.category', [$library['slug'], $category['slug']]) }}"><span class="category-number">0{{ $index + 1 }}</span><span class="category-icon" style="background: {{ $category['color'] }}">✦</span><strong>{{ $category['name'] }}</strong><p>{{ $category['description'] }}</p><span class="category-count">{{ count($category['books']) }} {{ count($category['books']) === 1 ? 'naslov' : 'naslova' }} <b>↗</b></span></a>@endforeach</div></section>
        <section class="public-wrap back-strip"><a class="text-link" href="{{ route('libraries.index') }}">← Nazad na biblioteke</a><span>Svaka biblioteka ima svoju priču.</span></section>
    </main>
    @include('public.partials.footer')
</div>
@endsection
