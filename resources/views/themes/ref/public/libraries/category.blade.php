@extends('themes.ref.layouts.public')

@section('title', \App\Support\Text::cyr($category['name']).' — '.\App\Support\Text::cyr($library['name']).' | Ебиблиотека Академије Филиповић')

@section('content')
<main>
    @include('themes.ref.public.partials.nav')

    <section class="page-hero">
        <div class="container page-hero-inner">
            <div>
                <div class="crumbs">
                    <a href="{{ route('home') }}">Почетна</a><span>/</span>
                    <a href="{{ route('libraries.index') }}">Каталог књига</a><span>/</span>
                    <a href="{{ route('libraries.show', $library['slug']) }}">{{ \App\Support\Text::cyr($library['name']) }}</a><span>/</span>
                    <span class="current">{{ \App\Support\Text::cyr($category['name']) }}</span>
                </div>
                <span class="section-kicker">КАТЕГОРИЈА У БИБЛИОТЕЦИ</span>
                <h1>{{ \App\Support\Text::cyr($category['name']) }}</h1>
                <p class="page-lead">{{ \App\Support\Text::cyr($category['description']) }}</p>
            </div>
            <div class="page-hero-aside">
                <div class="hero-stat-card">
                    <small>{{ \App\Support\Text::cyr($library['name']) }}</small>
                    <strong>{{ count($category['books']) }}</strong>
                    <span>{{ count($category['books']) === 1 ? 'наслов' : 'наслова' }}</span>
                </div>
            </div>
        </div>
    </section>

    <section class="section">
        <div class="container">
            <div class="lead-heading">
                <div>
                    <span class="section-kicker">{{ count($category['books']) }} {{ count($category['books']) === 1 ? 'НАСЛОВ' : 'НАСЛОВА' }}</span>
                    <h2>Књиге у овој <em>категорији</em></h2>
                </div>
                <p>Отворите наслов за више информација о књизи и доступности.</p>
            </div>

            <div class="book-list">
                @forelse ($category['books'] as $index => $book)
                    <a class="book-list-item" href="{{ route('libraries.book', [$library['slug'], $category['slug'], $book['slug']]) }}">
                        <span class="directory-index">{{ str_pad($index + 1, 2, '0', STR_PAD_LEFT) }}</span>
                        <span class="book-list-cover {{ ['cover-blue', 'cover-gold', 'cover-red', 'cover-green', 'cover-navy'][$index % 5] }}"><span>{{ mb_strtoupper(\App\Support\Text::cyr($book['title'])) }}</span></span>
                        <span class="book-list-copy">
                            <strong>{{ \App\Support\Text::cyr($book['title']) }}</strong>
                            <small>{{ \App\Support\Text::cyr($book['author']) }} · {{ $book['year'] }}</small>
                            <p>{{ \Illuminate\Support\Str::limit(\App\Support\Text::cyr($book['description']), 105) }}</p>
                        </span>
                        <span class="availability {{ $book['availability'] === 'Uskoro dostupno' ? 'availability--soon' : '' }}">{{ \App\Support\Text::cyr($book['availability']) }}</span>
                        <span class="arrow-link">→</span>
                    </a>
                @empty
                    <div class="no-results">
                        <h3>У овој категорији још нема књига.</h3>
                    </div>
                @endforelse
            </div>
        </div>
    </section>

    <section class="section section--plain">
        <div class="container back-strip">
            <a href="{{ route('libraries.show', $library['slug']) }}">← Све категорије</a>
            <span>{{ \App\Support\Text::cyr($library['name']) }}</span>
        </div>
    </section>

    @include('themes.ref.public.partials.footer')
</main>
@endsection
