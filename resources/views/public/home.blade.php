@extends('layouts.public')

@section('title', 'eBiblioteka | Svaka knjiga je nova pustolovina')

@section('content')
<div class="public-shell" id="top">
    <header class="public-nav">
        <a class="public-brand" href="#top" aria-label="eBiblioteka početna">
            <span class="public-brand-mark"><svg xmlns="http://www.w3.org/2000/svg" width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-book-open" aria-hidden="true"><path d="M12 5v16"></path><path d="M20.001 19A2 2 0 0022 17V5a2 2 0 00-1.999-2L16 3.002A5 5 0 0012 5a5 5 0 00-4-2H4a2 2 0 00-2 2v12a2 2 0 001.999 2H8a5 5 0 014 2 5 5 0 014-2z"></path></svg></span>
            <span>eBiblioteka</span>
        </a>
        <nav class="public-links" data-mobile-menu aria-label="Glavna navigacija">
            <a href="{{ route('libraries.index') }}">Katalog</a>
            <a href="{{ route('libraries.index') }}">Biblioteke</a>
            <a href="#preporuke">Preporuke</a>
            <a href="{{ route('project.about') }}">O projektu</a>
            <a href="{{ route('contact.create') }}">Kontakt</a>
            <a class="mobile-login" href="{{ \App\Support\FrontendUrl::url() }}">Prijava</a>
        </nav>
        <div class="public-actions">
            @include('public.partials.theme-switch', ['themeSwitchLang' => 'lat'])
            <a class="public-login" href="{{ \App\Support\FrontendUrl::url() }}">Prijava</a>
        </div>
        <button class="public-menu-toggle" data-menu-toggle type="button" aria-label="Otvori meni" aria-expanded="false">☰</button>
    </header>

    <main>
        <section class="public-wrap hero-section" id="katalog">
            <div class="hero-copy">
                <span class="section-label">DIGITALNI PROSTOR ZA ČITAOCE</span>
                <h1 class="display">Svaka knjiga je nova <span class="highlight">pustolovina.</span></h1>
                <p>Pronađite priču koja vas čeka. Istražite biblioteke, otkrijte nove naslove i napravite svoj mali kutak za čitanje.</p>
                <form class="hero-search" action="{{ route('libraries.index') }}" method="get">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"></circle><path d="m20 20-4-4"></path></svg>
                    <input name="q" type="search" placeholder="Pretražite naslove, autore..." aria-label="Pretražite katalog">
                    <button type="submit">Pretraži <span>↗</span></button>
                </form>
                <div class="hero-notes"><span>✓ Bez komplikovanja</span><span>✓ Za svakog čitaoca</span></div>
            </div>
            <div class="hero-illustration" aria-label="Ilustracija knjiga i školskog pribora" role="img">
                <div class="paper"><div class="paper-lines"></div></div>
                <div class="sun"></div>
                <div class="sticker sticker-star"><span>Čitaj!</span></div>
                <div class="sticker sticker-label">OVDE<br>POČINJE<br>PRIČA</div>
                <div class="book-stack">
                    <div class="book-shape book-one"><span class="book-title">otvori. istraži. sanjaj.</span><small class="book-small">eBIBLIOTEKA</small></div>
                    <div class="book-shape book-two"><span class="book-title">SVET JE U KNJIGAMA</span></div>
                    <div class="book-shape book-three"><span class="book-title">ČITAJ SVOJIM RITMOM</span></div>
                </div>
                <div class="pencil"></div><div class="scribble">✦</div>
            </div>
        </section>

        <section class="public-wrap promise-row" aria-label="Prednosti eBiblioteke">
            <div class="promise"><span class="promise-number">01</span><p>Jedno mesto<br>za sve biblioteke</p></div>
            <div class="promise"><span class="promise-number">02</span><p>Pretraga koja<br>štedi vreme</p></div>
            <div class="promise"><span class="promise-number">03</span><p>Više prostora<br>za dobru knjigu</p></div>
        </section>

        <section class="public-wrap about-section" id="o-projektu">
            <span class="section-label">ZAŠTO eBIBLIOTEKA</span>
            <div class="about-grid">
                <h2 class="display">Biblioteka ne mora da bude <span class="serif">komplikovana.</span></h2>
                <div class="about-copy"><p>Od prve preporuke do poslednje stranice, eBiblioteka okuplja ono što je važno: knjige, ljude i mesta na kojima se čita.</p><a class="text-link" href="{{ route('project.about') }}">Upoznajte projekat <span>↗</span></a></div>
            </div>
        </section>

        <section class="recommendation-section" id="preporuke">
            <div class="public-wrap">
                <div class="section-heading"><div><span class="section-label">ODABRANO ZA VAS</span><h2 class="display">Priče koje vredi <span class="serif">otvoriti.</span></h2></div><a class="round-link" href="#katalog" aria-label="Pogledajte katalog">→</a></div>
                <div class="recommendation-grid">
                    @foreach ($recommendations as $index => $recommendation)
                        <article class="recommendation-card">
                            <div class="book-cover {{ $recommendation['color'] }}"><div class="cover-top"><span>0{{ $index + 1 }}</span><span>eB</span></div><span class="cover-type">{{ $recommendation['type'] }}</span><strong>{{ $recommendation['title'] }}</strong><small>{{ $recommendation['author'] }}</small><span class="cover-brand">eBiblioteka</span></div>
                            <div class="card-meta"><span>Preporuka bibliotekara</span><span>↗</span></div>
                        </article>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="public-wrap library-section" id="biblioteke">
            <div class="library-intro"><span class="section-label">MREŽA BIBLIOTEKA</span><h2 class="display">Vaša sledeća <span class="serif">stanica.</span></h2><p>Upoznajte biblioteke koje svoje police otvaraju digitalno. Pronađite mesto za sebe, blizu vas.</p><a class="button button-outline" href="{{ route('libraries.index') }}">Pogledajte sve biblioteke <span>↗</span></a></div>
            <div class="library-list">
                @foreach ($libraries as $index => $library)
                    <a class="library-item" href="{{ route('libraries.show', $library['slug']) }}"><span class="library-number">0{{ $index + 1 }}</span><span class="library-emblem" style="background: {{ $library['color'] }}">{{ $library['mark'] }}</span><span class="library-name"><strong>{{ $library['name'] }}</strong><small>{{ $library['city'] }}</small></span><span class="library-count">{{ $library['count'] }}</span><span class="library-arrow">↗</span></a>
                @endforeach
                <div class="library-more">+ još 24 biblioteke uskoro</div>
            </div>
        </section>

        <section class="public-wrap news-section" id="novosti">
            <div class="section-heading"><div><span class="section-label">IZ NAŠE ZAJEDNICE</span><h2 class="display">Čitajte između <span class="serif">redova.</span></h2></div><a class="text-link" href="#novosti">Sve novosti <span>↗</span></a></div>
            <div class="news-grid">
                @foreach ($news as $index => $item)
                    <article class="news-card {{ $index === 0 ? 'news-card-featured' : '' }}"><div class="news-top"><span>{{ $item['category'] }}</span><span>{{ $item['date'] }}</span></div><h3>{{ $item['title'] }}</h3><a href="#novosti" aria-label="Pročitajte: {{ $item['title'] }}">↗</a></article>
                @endforeach
            </div>
        </section>

        <section class="public-wrap join-section" id="prijava">
            <div class="join-mark">✦</div><div><span class="section-label">OSTANIMO U KONTAKTU</span><h2 class="display">Uvek ima još jedna <span class="serif">dobra knjiga.</span></h2></div><div class="join-copy"><p>Prijavite se da saznate kada se otvori nova biblioteka ili stigne nova preporuka.</p><a class="button button-yellow" href="{{ \App\Support\FrontendUrl::url() }}">Prijavite se <span>↗</span></a></div>
        </section>
    </main>

        <footer class="public-wrap public-footer">
        <div class="footer-main"><a class="public-brand" href="#top"><span class="public-brand-mark"><svg xmlns="http://www.w3.org/2000/svg" width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-book-open" aria-hidden="true"><path d="M12 5v16"></path><path d="M20.001 19A2 2 0 0022 17V5a2 2 0 00-1.999-2L16 3.002A5 5 0 0012 5a5 5 0 00-4-2H4a2 2 0 00-2 2v12a2 2 0 001.999 2H8a5 5 0 014 2 5 5 0 014-2z"></path></svg></span><span>eBiblioteka</span></a><p>Biblioteka za moderna vremena.</p></div>
        <nav class="footer-links" aria-label="Podnožje"><a href="{{ route('libraries.index') }}">Katalog</a><a href="{{ route('libraries.index') }}">Biblioteke</a><a href="#preporuke">Preporuke</a><a href="{{ route('project.about') }}">O projektu</a><a href="{{ route('contact.create') }}">Kontakt</a></nav>
        <div class="footer-bottom"><span>© 2026 eBiblioteka</span><span>Sa pažnjom prema čitaocima.</span></div>
    </footer>
</div>
@endsection
