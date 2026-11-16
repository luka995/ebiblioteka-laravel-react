<section class="public-wrap faq-section" id="faq">
    <div class="faq-heading">
        <div>
            <span class="section-label">ЧЕСТО ПОСТАВЉАНА ПИТАЊА</span>
            <h2 class="display">Све што треба да знате <span class="serif">пре почетка.</span></h2>
        </div>
        <p>Од увођења и података до свакодневног рада — одговори на најчешћа питања.</p>
    </div>
    <div class="faq-list">
        @foreach ($faq as $item)
            <details class="faq-item" @if ($loop->first) open @endif>
                <summary><span>{{ $item['question'] }}</span><span class="faq-plus" aria-hidden="true">+</span></summary>
                <div class="faq-answer"><p>{{ $item['answer'] }}</p></div>
            </details>
        @endforeach
    </div>
    <div class="faq-actions">
        <a class="text-link" href="{{ route('contact.create', [], false) }}">Имате друго питање? Контактирајте нас <span>↗</span></a>
    </div>
</section>
