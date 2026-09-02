<footer class="ref-footer">
    <div class="container">
        <div class="footer-main">
            <a class="brand light" href="{{ route('home') }}" aria-label="Ебиблиотека — почетна">
                <span class="brand-mark">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 5v16"></path><path d="M20.001 19A2 2 0 0 0 22 17V5a2 2 0 0 0-1.999-2L16 3.002A5 5 0 0 0 12 5a5 5 0 0 0-4-2H4a2 2 0 0 0-2 2v12a2 2 0 0 0 1.999 2H8a5 5 0 0 1 4 2 5 5 0 0 1 4-2z"></path></svg>
                </span>
                <span>
                    <strong>еБиблиотека</strong>
                    <small>Академија Филиповић</small>
                </span>
            </a>
            <p>Дигитално решење за савремену школску библиотеку.</p>
        </div>
        <nav class="footer-links" aria-label="Подножје">
            <a href="{{ route('libraries.index') }}">Каталог књига</a>
            <a href="{{ route('libraries.index') }}">Библиотеке</a>
            <a href="{{ route('home') }}#novosti">Новости</a>
            <a href="{{ route('project.about') }}">О е-Библиотеци</a>
            <a href="{{ route('contact.create') }}">Контакт</a>
            <a href="https://akademijafilipovic.com" target="_blank" rel="noreferrer">Академија Филиповић ↗</a>
        </nav>
        <div class="footer-bottom"><span>© 2026 Академија Филиповић</span><span>Сва права задржана.</span></div>
    </div>
</footer>
