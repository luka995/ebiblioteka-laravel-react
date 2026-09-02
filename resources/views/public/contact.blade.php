@extends('layouts.public')

@section('title', 'Kontakt | eBiblioteka')

@section('content')
<div class="public-shell catalog-shell contact-shell" id="top">
    @include('public.partials.nav')

    <main>
        <section class="contact-hero public-wrap">
            <div>
                <div class="breadcrumbs"><a href="{{ route('home') }}">Početna</a><span>/</span><strong>Kontakt</strong></div>
                <span class="section-label">JAVITE NAM SE</span>
                <h1 class="display">Hajde da<br><span class="serif">razgovaramo.</span></h1>
                <p>Imate pitanje o eBiblioteci, želite da povežete svoju školu ili tražite rešenje za biblioteku? Pišite nam. Pravi ljudi iz Akademije Filipović odgovoriće vam u najkraćem roku.</p>
            </div>
            <div class="contact-hero-note">
                <span class="contact-note-mark">✦</span>
                <strong>Otvoreni<br>za dobru<br>ideju.</strong>
                <small>AKADEMIJA FILIPOVIĆ<br>eBIBLIOTEKA</small>
            </div>
        </section>

        <section class="contact-section public-wrap">
            <aside class="contact-info-card">
                <span class="section-label">O AKADEMIJI</span>
                <h2 class="display">Akademija<br><span class="serif">Filipović.</span></h2>
                <p>Akademija Filipović više od dve decenije razvija rešenja za savremeno obrazovanje. eBiblioteka je njihov prostor za jednostavniji, dostupniji i savremeniji rad školskih biblioteka.</p>
                <div class="contact-facts">
                    <a href="https://akademijafilipovic.com" target="_blank" rel="noreferrer"><span>WEB</span><strong>akademijafilipovic.com ↗</strong></a>
                    <a href="mailto:akademijafilipovic@gmail.com"><span>E-MAIL</span><strong>akademijafilipovic@gmail.com</strong></a>
                </div>
                <div class="contact-info-footer"><span class="contact-info-dot"></span><span>Odgovaramo na svaku poruku.</span></div>
            </aside>

            <div class="contact-form-card">
                <span class="section-label">KONTAKT FORMA</span>
                <h2 class="display">Da li imate<br><span class="serif">pitanje za nas?</span></h2>

                @if (session('contact_sent'))
                    <div class="contact-success" role="status">Poruka je poslata. Hvala vam što ste nam se javili.</div>
                @endif

                <form action="{{ route('contact.store') }}" method="post">
                    @csrf
                    <div class="contact-form-grid">
                        <div class="contact-field">
                            <label for="contact-name">Ime i prezime</label>
                            <input id="contact-name" name="name" value="{{ old('name') }}" required autocomplete="name">
                            @error('name')<small class="contact-error">{{ $message }}</small>@enderror
                        </div>
                        <div class="contact-field">
                            <label for="contact-email">E-mail adresa</label>
                            <input id="contact-email" name="email" type="email" value="{{ old('email') }}" required autocomplete="email">
                            @error('email')<small class="contact-error">{{ $message }}</small>@enderror
                        </div>
                    </div>
                    <div class="contact-field">
                        <label for="contact-organization">Ustanova ili organizacija <span>(opciono)</span></label>
                        <input id="contact-organization" name="organization" value="{{ old('organization') }}" autocomplete="organization">
                        @error('organization')<small class="contact-error">{{ $message }}</small>@enderror
                    </div>
                    <div class="contact-field">
                        <label for="contact-message">Vaša poruka</label>
                        <textarea id="contact-message" name="message" rows="6" required>{{ old('message') }}</textarea>
                        @error('message')<small class="contact-error">{{ $message }}</small>@enderror
                    </div>
                    <button class="button button-blue" type="submit">Pošaljite poruku <span>↗</span></button>
                </form>
            </div>
        </section>
    </main>

    @include('public.partials.footer')
</div>
@endsection
