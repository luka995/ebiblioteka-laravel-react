@extends('themes.ref.layouts.public')

@section('title', 'Ебиблиотека Академије Филиповић')

@section('content')
@php
    $markers = ['#276f8f', '#d7a344', '#a94843'];
    $catalogBooks = collect($libraries)
        ->flatMap(function (array $library) {
            return collect($library['categories'])->flatMap(function (array $category) use ($library) {
                return collect($category['books'])->map(fn (array $book) => [
                    'library' => $library,
                    'category' => $category,
                    'book' => $book,
                ]);
            });
        })
        ->take(5)
        ->values();
@endphp

<main>
    @include('themes.ref.public.partials.nav')

    <section class="hero">
        <div class="container hero-grid">
            <div class="hero-copy">
                <span class="eyebrow">
                    <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M11.017 2.814a1 1 0 0 1 1.966 0l1.051 5.558a2 2 0 0 0 1.594 1.594l5.558 1.051a1 1 0 0 1 0 1.966l-5.558 1.051a2 2 0 0 0-1.594 1.594l-1.051 5.558a1 1 0 0 1-1.966 0l-1.051-5.558a2 2 0 0 0-1.594-1.594l-5.558-1.051a1 1 0 0 1 0-1.966l5.558-1.051a2 2 0 0 0 1.594-1.594z"></path></svg>
                    Знање је сада ближе него икада
                </span>
                <h1>Ваша школска библиотека — <em>на једном месту.</em></h1>
                <p>Претражите књижни фонд, проверите доступност и резервишите књигу — брзо, једноставно и у сваком тренутку.</p>
                <form class="search-box" action="{{ route('libraries.index') }}" method="get">
                    <svg xmlns="http://www.w3.org/2000/svg" width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m21 21-4.34-4.34"></path><circle cx="11" cy="11" r="8"></circle></svg>
                    <input name="q" type="search" placeholder="Претражите библиотеке..." aria-label="Претрага књига" value="">
                    <button type="submit">Претражи</button>
                </form>
                <div class="quick-stats">
                    <span><strong>{{ count($libraries) }}</strong> библиотеке</span>
                    <i></i>
                    <span><strong>{{ count($recommendations) }}</strong> препоруке</span>
                    <i></i>
                    <span><strong>24/7</strong> приступ</span>
                </div>
            </div>
            <div class="hero-art" aria-hidden="true">
                <div class="sun"></div>
                <div class="arch"></div>
                <div class="shelf shelf-one"><b></b><b></b><b></b><b></b><b></b></div>
                <div class="shelf shelf-two"><b></b><b></b><b></b></div>
                <div class="plant"><i></i><i></i><i></i><span></span></div>
            </div>
        </div>
    </section>

    <section class="books-section">
        <div class="container">
            <div class="section-heading">
                <div>
                    <span class="section-kicker">ИЗДВАЈАМО ИЗ ФОНДА</span>
                    <h2>Књиге које вреди прочитати</h2>
                </div>
                <a href="{{ route('libraries.index') }}">Погледајте цео каталог
                    <svg xmlns="http://www.w3.org/2000/svg" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14"></path><path d="m12 5 7 7-7 7"></path></svg>
                </a>
            </div>

            <div class="category-row">
                <a class="active" href="{{ route('libraries.index') }}">Све књиге</a>
                @php $firstLibrary = $libraries[0] ?? null; @endphp
                @if ($firstLibrary)
                    @foreach (array_slice($firstLibrary['categories'], 0, 4) as $chipCategory)
                        <a href="{{ route('libraries.category', [$firstLibrary['slug'], $chipCategory['slug']]) }}">{{ \App\Support\Text::cyr($chipCategory['name']) }}</a>
                    @endforeach
                @endif
            </div>

                    <div class="book-grid">
                        @forelse ($catalogBooks as $index => $entry)
                            @php
                                $entryBook = $entry['book'];
                                $entryCover = ['cover-blue', 'cover-gold', 'cover-red', 'cover-green', 'cover-navy'][$index % 5];
                            @endphp
                            <article class="book-card">
                                <a href="{{ route('libraries.book', [$entry['library']['slug'], $entry['category']['slug'], $entryBook['slug']]) }}">
                                    <div class="book-cover {{ $entryCover }}">
                                        <span>
                                            @foreach (preg_split('/\s+/', \App\Support\Text::cyr($entryBook['title'])) as $word)
                                                <i>{{ mb_strtoupper($word) }}</i>
                                            @endforeach
                                        </span>
                                        <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 5v16"></path><path d="M20.001 19A2 2 0 0 0 22 17V5a2 2 0 0 0-1.999-2L16 3.002A5 5 0 0 0 12 5a5 5 0 0 0-4-2H4a2 2 0 0 0-2 2v12a2 2 0 0 0 1.999 2H8a5 5 0 0 1 4 2 5 5 0 0 1 4-2z"></path></svg>
                                    </div>
                                </a>
                                <span class="availability {{ $entryBook['availability'] === 'Uskoro dostupno' ? 'availability--soon' : '' }}">{{ \App\Support\Text::cyr($entryBook['availability']) }}</span>
                                <h3>{{ \App\Support\Text::cyr($entryBook['title']) }}</h3>
                                <p>{{ \App\Support\Text::cyr($entryBook['author']) }}</p>
                                <a class="book-detail-link" href="{{ route('libraries.book', [$entry['library']['slug'], $entry['category']['slug'], $entryBook['slug']]) }}">Детаљи и резервација
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 18 6-6-6-6"></path></svg>
                        </a>
                    </article>
                @empty
                    <div class="no-results">
                        <h3>Тренутно нема књига у понуди.</h3>
                        <p>Погледајте цео каталог.</p>
                    </div>
                @endforelse
            </div>
        </div>
    </section>

    <section class="news-section" id="novosti">
        <div class="container">
            <div class="section-heading">
                <div>
                    <span class="section-kicker">ИЗ НАШЕ ЗАЈЕДНИЦЕ</span>
                    <h2>Читајте између <em>редова</em></h2>
                </div>
                <a href="{{ route('home') }}#novosti">Све новости
                    <svg xmlns="http://www.w3.org/2000/svg" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14"></path><path d="m12 5 7 7-7 7"></path></svg>
                </a>
            </div>

            <div class="news-grid">
                @forelse ($news as $index => $item)
                    <article class="news-card {{ $index === 0 ? 'news-card--featured' : '' }}">
                        <div class="news-top"><span>{{ \App\Support\Text::cyr($item['category']) }}</span><span>{{ \App\Support\Text::cyr($item['date']) }}</span></div>
                        <h3>{{ \App\Support\Text::cyr($item['title']) }}</h3>
                    </article>
                @empty
                    <div class="no-results">
                        <h3>Тренутно нема новости.</h3>
                    </div>
                @endforelse
            </div>
        </div>
    </section>

    <section class="services">
        <div class="container service-grid">
            <div class="service-intro">
                <span class="section-kicker">СВЕ ШТО ВАМ ЈЕ ПОТРЕБНО</span>
                <h2>Библиотека која ради за вас</h2>
                <p>Савремено решење које повезује библиотекаре, наставнике и ученике и олакшава свакодневни рад.</p>
            </div>
            <article>
                <span>
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m21 21-4.34-4.34"></path><circle cx="11" cy="11" r="8"></circle></svg>
                </span>
                <h3>Претрага фонда</h3>
                <p>Брзо пронађите књигу по наслову, аутору, области или ISBN броју.</p>
            </article>
            <article>
                <span>
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"></circle><path d="M12 6v6h4"></path></svg>
                </span>
                <h3>Онлајн резервација</h3>
                <p>Резервишите доступну књигу и преузмите је у школској библиотеци.</p>
            </article>
            <article>
                <span>
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m16 6 4 14"></path><path d="M12 6v14"></path><path d="M8 8v12"></path><path d="M4 4v16"></path></svg>
                </span>
                <h3>Једноставна евиденција</h3>
                <p>Комплетна контрола фонда, задужења и извештаја за библиотекаре.</p>
            </article>
        </div>
    </section>

    <section class="section">
        <div class="container">
            <div class="lead-heading">
                <div>
                    <span class="section-kicker">БИБЛИОТЕКЕ У МРЕЖИ</span>
                    <h2>Изаберите своју <em>библиотеку</em></h2>
                </div>
                <p>Свака библиотека у мрежи отвара своје категорије, наслове и доступност.</p>
            </div>
            <div class="directory">
                @foreach ($libraries as $index => $library)
                    <a class="directory-card" href="{{ route('libraries.show', $library['slug']) }}">
                        <span class="directory-index">0{{ $index + 1 }}</span>
                        <span class="directory-emblem {{ $index % 2 ? 'gold' : '' }}" style="background: {{ $markers[$index % 3] }}">{{ $library['mark'] }}</span>
                        <span class="directory-copy">
                            <strong>{{ \App\Support\Text::cyr($library['name']) }}</strong>
                            <small>{{ \App\Support\Text::cyr($library['city']) }}</small>
                            <span>{{ count($library['categories']) }} категорија · {{ \App\Support\Text::cyr($library['count']) }}</span>
                        </span>
                        <span class="arrow-link">→</span>
                    </a>
                @endforeach
            </div>
        </div>
    </section>

    <section class="quote-section">
        <div class="container quote-inner">
            <blockquote>„Књига је сан који држите у руци.”</blockquote>
            <p>Откријте следећу књигу која ће променити ваш поглед на свет.</p>
            <a class="btn btn--gold" href="{{ route('libraries.index') }}">Истражите библиотеку</a>
        </div>
    </section>

    @include('themes.ref.public.partials.footer')
</main>
@endsection
