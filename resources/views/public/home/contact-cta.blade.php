<section class="public-wrap join-section" id="kontakt">
    <div class="join-mark">✦</div>
    <div>
        <span class="section-label">РЕГИСТРАЦИЈА И УПИТИ</span>
        <h2 class="display">Отворите библиотеку <span class="serif">или нам се јавите.</span></h2>
    </div>
    <div class="join-copy">
        <p>За цену, услове и демонстрацију пошаљите упит. Академија Филиповић одговара на сваку поруку.</p>
        <div class="join-actions">
            <a class="button button-yellow" href="{{ route('library-requests.create', [], false) }}">Региструјте школу <span>↗</span></a>
            <a class="button button-outline" href="{{ route('contact.create', [], false) }}">Пошаљите упит <span>↗</span></a>
        </div>
        <div class="join-contact">
            <a href="tel:{{ $contact['phone_href'] }}">{{ $contact['phone'] }}</a>
            <a href="mailto:{{ $contact['email'] }}">{{ $contact['email'] }}</a>
        </div>
    </div>
</section>
