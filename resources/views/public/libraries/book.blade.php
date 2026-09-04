@extends('layouts.public')

@section('title', \App\Support\Text::cyr($book['title']).' | еБиблиотека')

@section('content')
<div class="public-shell catalog-shell" id="top">
    @include('public.partials.nav')
    <main>
        <section class="public-wrap book-detail-section"><div class="breadcrumbs"><a href="{{ route('home') }}">Почетна</a><span>/</span><a href="{{ route('libraries.index') }}">Библиотеке</a><span>/</span><a href="{{ route('libraries.show', $library['slug']) }}">{{ \App\Support\Text::cyr($library['name']) }}</a><span>/</span><a href="{{ route('libraries.category', [$library['slug'], $category['slug']]) }}">{{ \App\Support\Text::cyr($category['name']) }}</a><span>/</span><strong>{{ \App\Support\Text::cyr($book['title']) }}</strong></div><div class="book-detail-grid"><div class="large-book-cover" style="background: {{ $category['color'] }}"><span>еБ</span><small>{{ \App\Support\Text::cyr($category['name']) }}</small><strong>{{ \App\Support\Text::cyr($book['title']) }}</strong><i>еБиблиотека</i></div><div class="book-detail-copy"><span class="section-label">ДЕТАЉ КЊИГЕ</span><h1 class="display">{{ \App\Support\Text::cyr($book['title']) }}</h1><p class="book-author">{{ \App\Support\Text::cyr($book['author']) }}</p><p class="book-description">{{ \App\Support\Text::cyr($book['description']) }}</p><dl class="book-facts"><div><dt>БИБЛИОТЕКА</dt><dd>{{ \App\Support\Text::cyr($library['name']) }}</dd></div><div><dt>ГОДИНА</dt><dd>{{ $book['year'] }}</dd></div><div><dt>ДОСТУПНОСТ</dt><dd class="book-available">{{ \App\Support\Text::cyr($book['availability']) }}</dd></div></dl><a class="button button-blue" href="{{ \App\Support\FrontendUrl::url() }}">Пријавите се за резервацију <span>↗</span></a><small class="reservation-note">Резервација ће бити доступна након пријаве на налог.</small></div></div></section>
        <section class="public-wrap back-strip"><a class="text-link" href="{{ route('libraries.category', [$library['slug'], $category['slug']]) }}">← Назад на {{ \App\Support\Text::cyr($category['name']) }}</a><span>{{ \App\Support\Text::cyr($library['name']) }}</span></section>
    </main>
    @include('public.partials.footer')
</div>
@endsection
