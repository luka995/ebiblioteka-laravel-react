@php($isHome = request()->routeIs('home'))
<header class="public-nav">
    <a class="public-brand" href="{{ $isHome ? '#top' : route('home', [], false) }}" aria-label="еБиблиотека почетна"><span class="public-brand-mark"><svg xmlns="http://www.w3.org/2000/svg" width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-book-open" aria-hidden="true"><path d="M12 5v16"></path><path d="M20.001 19A2 2 0 0022 17V5a2 2 0 00-1.999-2L16 3.002A5 5 0 0012 5a5 5 0 00-4-2H4a2 2 0 00-2 2v12a2 2 0 001.999 2H8a5 5 0 014 2 5 5 0 014-2z"></path></svg></span><span>еБиблиотека</span></a>
    <nav class="public-links" data-mobile-menu aria-label="Главна навигација">
        <a class="{{ request()->routeIs('libraries.*') ? 'active' : '' }}" href="{{ route('libraries.index', [], false) }}">Каталог</a>
        <a href="{{ route('libraries.index', [], false) }}">Библиотеке</a>
        <a href="{{ $isHome ? '#preporuke' : route('home', [], false).'#preporuke' }}">Препоруке</a>
        <a class="{{ request()->routeIs('news.*') ? 'active' : '' }}" href="{{ route('news.index', [], false) }}">Новости</a>
        <a href="{{ route('project.about', [], false) }}">О пројекту</a>
        <a class="{{ request()->routeIs('contact.*') ? 'active' : '' }}" href="{{ route('contact.create', [], false) }}">Контакт</a>
        <a class="mobile-login" href="{{ \App\Support\FrontendUrl::url() }}">Пријава</a>
    </nav>
    <div class="public-actions"><a class="button button-blue" href="{{ \App\Support\FrontendUrl::url() }}">Пријава</a></div>
    <button class="public-menu-toggle" data-menu-toggle type="button" aria-label="Отвори мени" aria-expanded="false">☰</button>
</header>
