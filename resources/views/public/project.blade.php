@extends('layouts.public')

@section('title', 'O projektu | eBiblioteka')

@section('content')
<div class="public-shell catalog-shell project-shell" id="top">
    @include('public.partials.nav')

    <main>
        <section class="project-hero public-wrap">
            <div class="project-hero-main">
                <div class="breadcrumbs"><a href="{{ route('home') }}">Početna</a><span>/</span><strong>O projektu</strong></div>
                <span class="section-label">NOVA VERZIJA eBIBLIOTEKE</span>
                <h1 class="display">Biblioteka<br><span class="serif">koja prati</span><br>vaš dan.</h1>
                <p class="project-lead">Akademija Filipović razvija novu verziju softvera <strong>eBiblioteka.rs</strong> — sa manje administracije za bibliotekare i jednostavnijim pristupom knjigama za učenike, nastavnike i roditelje.</p>
                <div class="project-hero-actions"><a class="button button-blue" href="{{ route('libraries.index') }}">Istražite katalog <span>↗</span></a><a class="text-link" href="https://akademijafilipovic.com" target="_blank" rel="noreferrer">Saznajte više o Akademiji <span>↗</span></a></div>
            </div>
            <div class="project-hero-panel">
                <div class="project-panel-top"><span>eBiblioteka</span><span>01 / 04</span></div>
                <div class="project-panel-title">Od police<br>do podataka.</div>
                <div class="project-panel-flow"><div><b>01</b><span>Skeniraj</span><small>Kamerom telefona</small></div><i>↓</i><div><b>02</b><span>Evidentiraj</span><small>Zaduženje ili razduženje</small></div><i>↓</i><div><b>03</b><span>Prati</span><small>Rokove i dostupnost</small></div></div>
                <div class="project-panel-footer"><span class="project-panel-dot"></span> Jednostavniji rad, svakog dana <strong>↗</strong></div>
            </div>
        </section>

        <section class="project-intro public-wrap">
            <div class="project-intro-heading"><span class="section-label">ZAŠTO NOVA VERZIJA</span><h2 class="display">Tehnologija treba da<br><span class="serif">skloni prepreke.</span></h2></div>
            <div class="project-intro-copy"><p>Akademija Filipović više od dve decenije razvija rešenja za savremeno obrazovanje. eBiblioteka je nastala iz potrebe da školska biblioteka bude pregledna, dostupna i korisna u svakodnevnom radu — ne još jedna komplikovana obaveza.</p><p>Nova verzija zadržava ono što je provereno u praksi: katalog, bar-kod evidenciju, zaduženje, razduženje, rezervacije i izveštaje. Istovremeno uvodi brže tokove rada i iskustvo prilagođeno telefonu.</p></div>
            <div class="project-proof"><div><strong>2003.</strong><span>Akademija Filipović<br>počinje rad</span></div><div><strong>2013.</strong><span>eBiblioteka dobija<br>međunarodno priznanje</span></div><div><strong>80.000+</strong><span>polaznika programa<br>stručnog usavršavanja</span></div></div>
        </section>

        <section class="project-capabilities">
            <div class="public-wrap">
                <div class="project-capabilities-heading"><div><span class="section-label">FUNKCIONALNOSTI</span><h2 class="display">Sve što je važno.<br><span class="serif">Bez suvišnih koraka.</span></h2></div><p>Nova eBiblioteka povezuje bibliotekare, škole i čitaoce oko istih, jasnih podataka.</p></div>
                <div class="project-capability-list">
                    <article class="project-capability"><span class="project-capability-number">01</span><div class="project-capability-icon">⌁</div><div><h3>Skeniranje knjiga mobilnim telefonom</h3><p>Kamera telefona prepoznaje bar-kod knjige, pa bibliotekar može brže da pronađe primerak i evidentira zaduženje ili razduženje — bez posebnog čitača na svakom radnom mestu.</p></div><span class="project-capability-arrow">↗</span></article>
                    <article class="project-capability"><span class="project-capability-number">02</span><div class="project-capability-icon">⌕</div><div><h3>Katalog dostupan na svakom uređaju</h3><p>Pretraga po naslovu, autoru, izdavaču i drugim podacima radi pregledno na računaru, tabletu i telefonu. Čitalac odmah vidi šta je dostupno.</p></div><span class="project-capability-arrow">↗</span></article>
                    <article class="project-capability"><span class="project-capability-number">03</span><div class="project-capability-icon">↔</div><div><h3>Jednostavne rezervacije i rokovi</h3><p>Zaduženja, razduženja i rezervacije prate jedan jasan tok. Obaveštenja podsećaju na rokove i informišu korisnika kada je tražena knjiga dostupna.</p></div><span class="project-capability-arrow">↗</span></article>
                    <article class="project-capability"><span class="project-capability-number">04</span><div class="project-capability-icon">◎</div><div><h3>Podaci koji rade za biblioteku</h3><p>Izveštaji i statistike o fondu, pozajmicama, najtraženijim naslovima i radu biblioteke nastaju iz evidencije koju već vodite.</p></div><span class="project-capability-arrow">↗</span></article>
                </div>
            </div>
        </section>

        <section class="project-audience public-wrap">
            <div class="project-audience-heading"><span class="section-label">ZA KOGA JE eBIBLIOTEKA</span><h2 class="display">Jedan sistem.<br><span class="serif">Tri bolja pogleda.</span></h2></div>
            <div class="project-audience-list"><div><span>01 / BIBLIOTEKAR</span><p><strong>Vodi fond, ne papir.</strong> Brže evidentiranje, tačniji podaci i pregled korisnika, rokova i dostupnosti na jednom mestu.</p></div><div><span>02 / ŠKOLA</span><p><strong>Vidi širu sliku.</strong> Uređen katalog, pouzdani izveštaji i mogućnost povezivanja više biblioteka u jednu mrežu.</p></div><div><span>03 / ČITALAC</span><p><strong>Pronađi knjigu bez čekanja.</strong> Pretraga, dostupnost, rezervacija i pregled zaduženja sa računara ili mobilnog telefona.</p></div></div>
        </section>

        <section class="project-cta public-wrap">
            <div><span class="section-label">POGLEDAJTE KAKO RADI</span><h2 class="display">Dobra biblioteka<br><span class="serif">počinje dobrim podacima.</span></h2></div>
            <div class="project-cta-copy"><p>Istražite javni katalog nove eBiblioteke i pogledajte kako biblioteke, kategorije i naslovi izgledaju u novom digitalnom prostoru.</p><a class="button button-yellow" href="{{ route('libraries.index') }}">Otvorite katalog <span>↗</span></a></div>
        </section>
    </main>

    @include('public.partials.footer')
</div>
@endsection
