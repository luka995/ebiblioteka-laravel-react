<header class="topbar">
    <div class="container nav-wrap">
        <a class="brand" href="{{ route('home') }}" aria-label="Ебиблиотека — почетна">
            <span class="brand-mark">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 5v16"></path><path d="M20.001 19A2 2 0 0 0 22 17V5a2 2 0 0 0-1.999-2L16 3.002A5 5 0 0 0 12 5a5 5 0 0 0-4-2H4a2 2 0 0 0-2 2v12a2 2 0 0 0 1.999 2H8a5 5 0 0 1 4 2 5 5 0 0 1 4-2z"></path></svg>
            </span>
            <span>
                <strong>еБиблиотека</strong>
                <small>Академија Филиповић</small>
            </span>
        </a>
        <nav class="nav-links" data-mobile-menu aria-label="Главна навигација">
            <a class="{{ request()->routeIs('libraries.*') ? 'active' : '' }}" href="{{ route('libraries.index') }}">Каталог књига</a>
            <a href="{{ route('libraries.index') }}">Библиотеке</a>
            <a href="{{ route('home') }}#novosti">Новости</a>
            <a class="{{ request()->routeIs('project.about') ? 'active' : '' }}" href="{{ route('project.about') }}">О е-Библиотеци</a>
            <a class="{{ request()->routeIs('contact.*') ? 'active' : '' }}" href="{{ route('contact.create') }}">Контакт</a>
        </nav>
        @include('public.partials.theme-switch', ['themeSwitchLang' => 'cyr'])
        <a class="login" href="{{ \App\Support\FrontendUrl::url() }}">
            <svg xmlns="http://www.w3.org/2000/svg" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="8" r="5"></circle><path d="M20 21a8 8 0 0 0-16 0"></path></svg>
            Пријавите се
        </a>
        <button class="menu-btn" data-menu-toggle type="button" aria-label="Отвори мени" aria-expanded="false">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 5h16"></path><path d="M4 12h16"></path><path d="M4 19h16"></path></svg>
        </button>
    </div>
</header>
