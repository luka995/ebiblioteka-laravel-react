<section class="guide-section" id="vodich">
    <div class="public-wrap guide-inner">
        <div class="guide-heading">
            <div>
                <span class="section-label">КАКО ПОЧЕТИ</span>
                <h2 class="display">Од регистрације <span class="serif">до прве позајмице.</span></h2>
            </div>
            <p>Шест корака којима школа уводи библиотеку у свакодневни рад — без дугих припрема и папирологије.</p>
        </div>
        <div class="guide-grid">
            @foreach ($guide as $index => $step)
                <article class="guide-step">
                    <span class="guide-number">0{{ $index + 1 }}</span>
                    <h3>{{ $step['title'] }}</h3>
                    <p>{{ $step['text'] }}</p>
                </article>
            @endforeach
        </div>
        <div class="guide-actions">
            <a class="button button-blue" href="{{ route('library-requests.create', [], false) }}">Региструјте школу <span>↗</span></a>
            <a class="text-link" href="{{ route('contact.create', [], false) }}">Питајте нас <span>↗</span></a>
        </div>
    </div>
</section>
