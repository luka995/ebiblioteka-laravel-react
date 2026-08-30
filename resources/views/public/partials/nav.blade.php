<header class="public-nav">
    <a class="public-brand" href="{{ route('home') }}" aria-label="eBiblioteka početna"><span class="public-brand-mark">✦</span><span>eBiblioteka</span></a>
    <nav class="public-links" data-mobile-menu aria-label="Glavna navigacija">
        <a class="active" href="{{ route('libraries.index') }}">Katalog</a>
        <a href="{{ route('libraries.index') }}">Biblioteke</a>
        <a href="{{ route('home') }}#preporuke">Preporuke</a>
        <a href="{{ route('project.about') }}">O projektu</a>
        <a class="mobile-login" href="http://localhost:3001">Prijava</a>
    </nav>
    <div class="public-actions"><a class="public-login" href="http://localhost:3001">Prijava</a><a class="button button-blue" href="http://localhost:3001">Kreiraj nalog <span>↗</span></a></div>
    <button class="public-menu-toggle" data-menu-toggle type="button" aria-label="Otvori meni" aria-expanded="false">☰</button>
</header>
