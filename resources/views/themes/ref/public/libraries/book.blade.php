@extends('themes.ref.layouts.public')

@section('title', \App\Support\Text::cyr($book['title']).' | Ебиблиотека Академије Филиповић')

@section('content')
<main>
    @include('themes.ref.public.partials.nav')

    <section class="page-hero">
        <div class="container page-hero-inner" style="min-height:0">
            <div class="crumbs">
                <a href="{{ route('home') }}">Почетна</a><span>/</span>
                <a href="{{ route('libraries.index') }}">Каталог књига</a><span>/</span>
                <a href="{{ route('libraries.show', $library['slug']) }}">{{ \App\Support\Text::cyr($library['name']) }}</a><span>/</span>
                <a href="{{ route('libraries.category', [$library['slug'], $category['slug']]) }}">{{ \App\Support\Text::cyr($category['name']) }}</a><span>/</span>
                <span class="current">{{ \App\Support\Text::cyr($book['title']) }}</span>
            </div>
        </div>
    </section>

    <section class="detail-wrap">
        <div class="container detail-grid">
            <div class="large-book {{ ['cover-blue', 'cover-gold', 'cover-red', 'cover-green', 'cover-navy'][(mb_strlen($book['slug']) + mb_strlen($category['slug'])) % 5] }}">
                <span>
                    @foreach (preg_split('/\s+/', \App\Support\Text::cyr($book['title'])) as $word)
                        <i>{{ mb_strtoupper($word) }}</i>
                    @endforeach
                </span>
                <small>{{ \App\Support\Text::cyr($category['name']) }}</small>
            </div>
            <div class="detail-copy">
                <span class="section-kicker">ДЕТАЉ КЊИГЕ</span>
                <h1>{{ \App\Support\Text::cyr($book['title']) }}</h1>
                <p class="book-author">{{ \App\Support\Text::cyr($book['author']) }}</p>
                <p class="book-description">{{ \App\Support\Text::cyr($book['description']) }}</p>
                <dl class="book-facts">
                    <div><dt>Библиотека</dt><dd>{{ \App\Support\Text::cyr($library['name']) }}</dd></div>
                    <div><dt>Година</dt><dd>{{ $book['year'] }}</dd></div>
                    <div><dt>Доступност</dt><dd>{{ \App\Support\Text::cyr($book['availability']) }}</dd></div>
                </dl>
                <a class="btn" href="{{ \App\Support\FrontendUrl::url() }}">Пријавите се за резервацију →</a>
                <small class="reservation-note">Резервација ће бити доступна након пријаве на налог.</small>
            </div>
        </div>
    </section>

    <section class="section section--plain">
        <div class="container back-strip">
            <a href="{{ route('libraries.category', [$library['slug'], $category['slug']]) }}">← Назад на {{ \App\Support\Text::cyr($category['name']) }}</a>
            <span>{{ \App\Support\Text::cyr($library['name']) }}</span>
        </div>
    </section>

    @include('themes.ref.public.partials.footer')
</main>
@endsection
