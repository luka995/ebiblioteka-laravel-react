<header class="public-nav">
    <a class="public-brand" href="{{ route('home') }}" aria-label="eBiblioteka početna"><span class="public-brand-mark"><svg xmlns="http://www.w3.org/2000/svg" width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-book-open" aria-hidden="true"><path d="M12 5v16"></path><path d="M20.001 19A2 2 0 0022 17V5a2 2 0 00-1.999-2L16 3.002A5 5 0 0012 5a5 5 0 00-4-2H4a2 2 0 00-2 2v12a2 2 0 001.999 2H8a5 5 0 014 2 5 5 0 014-2z"></path></svg></span><span>eBiblioteka</span></a>
    <nav class="public-links" data-mobile-menu aria-label="Glavna navigacija">
        <a class="{{ request()->routeIs('libraries.*') ? 'active' : '' }}" href="{{ route('libraries.index') }}">Katalog</a>
        <a href="{{ route('libraries.index') }}">Biblioteke</a>
        <a href="{{ route('home') }}#preporuke">Preporuke</a>
        <a href="{{ route('project.about') }}">O projektu</a>
        <a class="{{ request()->routeIs('contact.*') ? 'active' : '' }}" href="{{ route('contact.create') }}">Kontakt</a>
        <a class="mobile-login" href="{{ \App\Support\FrontendUrl::url() }}">Prijava</a>
    </nav>
    <div class="public-actions">@include('public.partials.theme-switch', ['themeSwitchLang' => 'lat'])<a class="public-login" href="{{ \App\Support\FrontendUrl::url() }}">Prijava</a><a class="button button-blue" href="{{ \App\Support\FrontendUrl::url() }}">Kreiraj nalog <span>↗</span></a></div>
    <button class="public-menu-toggle" data-menu-toggle type="button" aria-label="Otvori meni" aria-expanded="false">☰</button>
</header>
