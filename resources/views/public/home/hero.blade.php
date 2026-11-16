<section class="public-wrap hero-section" id="pocetna">
    <div class="hero-copy">
        <span class="section-label">СОФТВЕР ЗА ШКОЛСКЕ БИБЛИОТЕКЕ</span>
        <h1 class="display">Комплетан софтвер за вашу <span class="highlight">школску библиотеку.</span></h1>
        <p>Књиге, ученици, разреди и позајмице на једном месту — увек се зна шта је задужено, код кога и до када.</p>
        <div class="hero-actions">
            <a class="button button-blue" href="{{ route('library-requests.create', [], false) }}">Региструјте школу <span>↗</span></a>
            <a class="button button-outline" href="#demo">Пробајте демо <span>↗</span></a>
        </div>
        <form class="hero-search" action="{{ route('libraries.index', [], false) }}" method="get">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"></circle><path d="m20 20-4-4"></path></svg>
            <input name="q" type="search" placeholder="Претражите наслове, ауторе..." aria-label="Претражите каталог">
            <button type="submit">Претражи <span>↗</span></button>
        </form>
        <div class="hero-notes"><span>✓ Без инсталације</span><span>✓ Рад у прегледачу</span><span>✓ За основне и средње школе</span></div>
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
