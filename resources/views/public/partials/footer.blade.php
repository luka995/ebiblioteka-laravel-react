@php($home = route('home', [], false))
@php($contact = config('public_site.contact'))
<footer class="public-wrap public-footer">
    <div class="footer-grid">
        <div class="footer-brand">
            <a class="public-brand" href="{{ $home }}"><span class="public-brand-mark"><svg xmlns="http://www.w3.org/2000/svg" width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-book-open" aria-hidden="true"><path d="M12 5v16"></path><path d="M20.001 19A2 2 0 0022 17V5a2 2 0 00-1.999-2L16 3.002A5 5 0 0012 5a5 5 0 00-4-2H4a2 2 0 00-2 2v12a2 2 0 001.999 2H8a5 5 0 014 2 5 5 0 014-2z"></path></svg></span><span>еБиблиотека</span></a>
            <p>Софтвер за модерне школске библиотеке. Каталог, позајмице, инвентар и извештаји на једном месту.</p>
            <a class="button button-blue" href="{{ route('library-requests.create', [], false) }}">Региструјте школу <span>↗</span></a>
            <div class="footer-contact">
                <a href="tel:{{ $contact['phone_href'] }}">{{ $contact['phone'] }}</a>
                <a href="mailto:{{ $contact['email'] }}">{{ $contact['email'] }}</a>
            </div>
        </div>

        <div class="footer-col">
            <h2>ПРОИЗВОД</h2>
            <a href="{{ $home }}#resenje">Решење</a>
            <a href="{{ $home }}#demo">Демо</a>
            <a href="{{ $home }}#skole">За школе</a>
            <a href="{{ route('libraries.index', [], false) }}">Каталог</a>
            <a href="{{ route('news.index', [], false) }}">Новости</a>
        </div>

        <div class="footer-col">
            <h2>ПОДРШКА</h2>
            <a href="{{ $home }}#vodich">Како почети</a>
            <a href="{{ $home }}#faq">Често постављана питања</a>
            <a href="{{ route('contact.create', [], false) }}">Контакт</a>
            <a href="{{ route('library-requests.create', [], false) }}">Регистрација школе</a>
        </div>

        <div class="footer-col">
            <h2>КОМПАНИЈА</h2>
            <a href="{{ route('project.about', [], false) }}">О пројекту</a>
            <a href="{{ $contact['website'] }}" target="_blank" rel="noreferrer">Академија Филиповић ↗</a>
            <a href="tel:{{ $contact['phone_href'] }}">{{ $contact['phone'] }}</a>
            <a href="mailto:{{ $contact['email'] }}">{{ $contact['email'] }}</a>
        </div>
    </div>

    <div class="footer-bottom"><span>© 2026 еБиблиотека</span><span>Софтвер за школске библиотеке · Академија Филиповић</span></div>
</footer>
