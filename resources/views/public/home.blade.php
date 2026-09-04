@extends('layouts.public')

@section('title', 'еБиблиотека | Свака књига је нова пустоловина')

@section('content')
<div class="public-shell" id="top">
    <header class="public-nav">
        <a class="public-brand" href="#top" aria-label="еБиблиотека почетна">
            <span class="public-brand-mark"><svg xmlns="http://www.w3.org/2000/svg" width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-book-open" aria-hidden="true"><path d="M12 5v16"></path><path d="M20.001 19A2 2 0 0022 17V5a2 2 0 00-1.999-2L16 3.002A5 5 0 0012 5a5 5 0 00-4-2H4a2 2 0 00-2 2v12a2 2 0 001.999 2H8a5 5 0 014 2 5 5 0 014-2z"></path></svg></span>
            <span>еБиблиотека</span>
        </a>
        <nav class="public-links" data-mobile-menu aria-label="Главна навигација">
            <a href="{{ route('libraries.index') }}">Каталог</a>
            <a href="{{ route('libraries.index') }}">Библиотеке</a>
            <a href="#preporuke">Препоруке</a>
            <a href="{{ route('project.about') }}">О пројекту</a>
            <a href="{{ route('contact.create') }}">Контакт</a>
            <a class="mobile-login" href="{{ \App\Support\FrontendUrl::url() }}">Пријава</a>
        </nav>
        <div class="public-actions">
            @include('public.partials.theme-switch', ['themeSwitchLang' => 'cyr'])
            <a class="public-login" href="{{ \App\Support\FrontendUrl::url() }}">Пријава</a>
        </div>
        <button class="public-menu-toggle" data-menu-toggle type="button" aria-label="Отвори мени" aria-expanded="false">☰</button>
    </header>

    <main>
        <section class="public-wrap hero-section" id="katalog">
            <div class="hero-copy">
                <span class="section-label">ДИГИТАЛНИ ПРОСТОР ЗА ЧИТАОЦЕ</span>
                <h1 class="display">Ваша школска библиотека — <span class="highlight">на једном месту.</span></h1>
                <p>Претражите књижни фонд, проверите доступност и резервишите књигу — брзо, једноставно и у сваком тренутку.</p>
                <form class="hero-search" action="{{ route('libraries.index') }}" method="get">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"></circle><path d="m20 20-4-4"></path></svg>
                    <input name="q" type="search" placeholder="Претражите наслове, ауторе..." aria-label="Претражите каталог">
                    <button type="submit">Претражи <span>↗</span></button>
                </form>
                <div class="hero-notes"><span>✓ Без компликовања</span><span>✓ За сваког читаоца</span></div>
            </div>
            <div class="hero-illustration" aria-label="Илустрација књига и школског прибора" role="img">
                <div class="paper"><div class="paper-lines"></div></div>
                <div class="sun"></div>
                <div class="sticker sticker-star"><span>Читај!</span></div>
                <div class="sticker sticker-label">ОВДЕ<br>ПОЧИЊЕ<br>ПРИЧА</div>
                <div class="book-stack">
                    <div class="book-shape book-one"><span class="book-title">отвори. истражи. сањај.</span><small class="book-small">еБИБЛИОТЕКА</small></div>
                    <div class="book-shape book-two"><span class="book-title">СВЕТ ЈЕ У КЊИГАМА</span></div>
                    <div class="book-shape book-three"><span class="book-title">ЧИТАЈ СВОЈИМ РИТМОМ</span></div>
                </div>
                <div class="pencil"></div><div class="scribble">✦</div>
            </div>
        </section>

        <section class="public-wrap promise-row" aria-label="Предности еБиблиотеке">
            <div class="promise"><span class="promise-number">01</span><p>Једно место<br>за све библиотеке</p></div>
            <div class="promise"><span class="promise-number">02</span><p>Претрага која<br>штеди време</p></div>
            <div class="promise"><span class="promise-number">03</span><p>Више простора<br>за добру књигу</p></div>
        </section>

        <section class="public-wrap about-section" id="o-projektu">
            <span class="section-label">ЗАШТО еБИБЛИОТЕКА</span>
            <div class="about-grid">
                <h2 class="display">Библиотека не мора да буде <span class="serif">компликована.</span></h2>
                <div class="about-copy"><p>Од прве препоруке до последње странице, еБиблиотека окупља оно што је важно: књиге, људе и места на којима се чита.</p><a class="text-link" href="{{ route('project.about') }}">Упознајте пројекат <span>↗</span></a></div>
            </div>
        </section>

        <section class="recommendation-section" id="preporuke">
            <div class="public-wrap">
                <div class="section-heading"><div><span class="section-label">ОДАБРАНО ЗА ВАС</span><h2 class="display">Приче које вреди <span class="serif">отворити.</span></h2></div><a class="round-link" href="#katalog" aria-label="Погледајте каталог">→</a></div>
                <div class="recommendation-grid">
                    @foreach ($recommendations as $index => $recommendation)
                        <article class="recommendation-card">
                            <div class="book-cover {{ $recommendation['color'] }}"><div class="cover-top"><span>0{{ $index + 1 }}</span><span>еБ</span></div><span class="cover-type">{{ \App\Support\Text::cyr($recommendation['type']) }}</span><strong>{{ \App\Support\Text::cyr($recommendation['title']) }}</strong><small>{{ \App\Support\Text::cyr($recommendation['author']) }}</small><span class="cover-brand">еБиблиотека</span></div>
                            <div class="card-meta"><span>Препорука библиотекара</span><span>↗</span></div>
                        </article>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="public-wrap library-section" id="biblioteke">
            <div class="library-intro"><span class="section-label">МРЕЖА БИБЛИОТЕКА</span><h2 class="display">Ваша следећа <span class="serif">станица.</span></h2><p>Упознајте библиотеке које своје полице отварају дигитално. Пронађите место за себе, близу вас.</p><a class="button button-outline" href="{{ route('libraries.index') }}">Погледајте све библиотеке <span>↗</span></a></div>
            <div class="library-list">
                @foreach ($libraries as $index => $library)
                    <a class="library-item" href="{{ route('libraries.show', $library['slug']) }}"><span class="library-number">0{{ $index + 1 }}</span><span class="library-emblem" style="background: {{ $library['color'] }}">{{ $library['mark'] }}</span><span class="library-name"><strong>{{ \App\Support\Text::cyr($library['name']) }}</strong><small>{{ \App\Support\Text::cyr($library['city']) }}</small></span><span class="library-count">{{ \App\Support\Text::cyr($library['count']) }}</span><span class="library-arrow">↗</span></a>
                @endforeach
                <div class="library-more">+ још 24 библиотеке ускоро</div>
            </div>
        </section>

        <section class="public-wrap news-section" id="novosti">
            <div class="section-heading"><div><span class="section-label">ИЗ НАШЕ ЗАЈЕДНИЦЕ</span><h2 class="display">Читајте између <span class="serif">редова.</span></h2></div><a class="text-link" href="#novosti">Све новости <span>↗</span></a></div>
            <div class="news-grid">
                @foreach ($news as $index => $item)
                    <article class="news-card {{ $index === 0 ? 'news-card-featured' : '' }}"><div class="news-top"><span>{{ \App\Support\Text::cyr($item['category']) }}</span><span>{{ \App\Support\Text::cyr($item['date']) }}</span></div><h3>{{ \App\Support\Text::cyr($item['title']) }}</h3><a href="#novosti" aria-label="Прочитајте: {{ \App\Support\Text::cyr($item['title']) }}">↗</a></article>
                @endforeach
            </div>
        </section>

        <section class="public-wrap join-section" id="prijava">
            <div class="join-mark">✦</div><div><span class="section-label">ОСТАНИМО У КОНТАКТУ</span><h2 class="display">Увек има још једна <span class="serif">добра књига.</span></h2></div><div class="join-copy"><p>Пријавите се да сазнате када се отвори нова библиотека или стигне нова препорука.</p><a class="button button-yellow" href="{{ \App\Support\FrontendUrl::url() }}">Пријавите се <span>↗</span></a></div>
        </section>
    </main>

        <footer class="public-wrap public-footer">
        <div class="footer-main"><a class="public-brand" href="#top"><span class="public-brand-mark"><svg xmlns="http://www.w3.org/2000/svg" width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-book-open" aria-hidden="true"><path d="M12 5v16"></path><path d="M20.001 19A2 2 0 0022 17V5a2 2 0 00-1.999-2L16 3.002A5 5 0 0012 5a5 5 0 00-4-2H4a2 2 0 00-2 2v12a2 2 0 001.999 2H8a5 5 0 014 2 5 5 0 014-2z"></path></svg></span><span>еБиблиотека</span></a><p>Библиотека за модерна времена.</p></div>
        <nav class="footer-links" aria-label="Подножје"><a href="{{ route('libraries.index') }}">Каталог</a><a href="{{ route('libraries.index') }}">Библиотеке</a><a href="#preporuke">Препоруке</a><a href="{{ route('project.about') }}">О пројекту</a><a href="{{ route('contact.create') }}">Контакт</a></nav>
        <div class="footer-bottom"><span>© 2026 еБиблиотека</span><span>Са пажњом према читаоцима.</span></div>
    </footer>
</div>
@endsection
