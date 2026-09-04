@extends('layouts.public')

@section('title', \App\Support\Text::cyr($category['name']).' - '.\App\Support\Text::cyr($library['name']).' | еБиблиотека')

@section('content')
<div class="public-shell catalog-shell" id="top">
    @include('public.partials.nav')
    <main>
        <section class="public-wrap listing-hero"><div class="breadcrumbs"><a href="{{ route('home') }}">Почетна</a><span>/</span><a href="{{ route('libraries.index') }}">Библиотеке</a><span>/</span><a href="{{ route('libraries.show', $library['slug']) }}">{{ \App\Support\Text::cyr($library['name']) }}</a><span>/</span><strong>{{ \App\Support\Text::cyr($category['name']) }}</strong></div><div class="listing-title"><span class="category-icon" style="background: {{ $category['color'] }}">✦</span><div><span class="section-label">КАТЕГОРИЈА У БИБЛИОТЕЦИ</span><h1 class="display">{{ \App\Support\Text::cyr($category['name']) }}</h1><p>{{ \App\Support\Text::cyr($category['description']) }}</p></div></div></section>
        <section class="public-wrap book-list-section"><div class="results-heading"><div><span class="section-label">{{ count($category['books']) }} {{ count($category['books']) === 1 ? 'НАСЛОВ' : 'НАСЛОВА' }}</span><h2 class="display">Књиге у овој<br><span class="serif">категорији.</span></h2></div><p>Отворите наслов за више информација о књизи и доступности.</p></div><div class="book-list">@foreach ($category['books'] as $index => $book)<a class="book-list-item" href="{{ route('libraries.book', [$library['slug'], $category['slug'], $book['slug']]) }}"><span class="book-list-number">0{{ $index + 1 }}</span><span class="mini-cover" style="background: {{ $category['color'] }}"><b>еБ</b><i>{{ \App\Support\Text::cyr($category['name']) }}</i></span><span class="book-list-copy"><strong>{{ \App\Support\Text::cyr($book['title']) }}</strong><small>{{ \App\Support\Text::cyr($book['author']) }} · {{ $book['year'] }}</small><span>{{ \App\Support\Text::cyr(\Illuminate\Support\Str::limit($book['description'], 105)) }}</span></span><span class="availability">{{ \App\Support\Text::cyr($book['availability']) }}</span><span class="book-list-arrow">↗</span></a>@endforeach</div></section>
        <section class="public-wrap back-strip"><a class="text-link" href="{{ route('libraries.show', $library['slug']) }}">← Све категорије</a><span>{{ \App\Support\Text::cyr($library['name']) }}</span></section>
    </main>
    @include('public.partials.footer')
</div>
@endsection
