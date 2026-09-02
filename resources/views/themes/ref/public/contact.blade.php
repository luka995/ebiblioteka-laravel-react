@extends('themes.ref.layouts.public')

@section('title', 'Контакт | Ебиблиотека Академије Филиповић')

@section('content')
<main>
    @include('themes.ref.public.partials.nav')

    <section class="page-hero">
        <div class="container page-hero-inner">
            <div>
                <div class="crumbs"><a href="{{ route('home') }}">Почетна</a><span>/</span><span class="current">Контакт</span></div>
                <span class="section-kicker">ЈАВИТЕ НАМ СЕ</span>
                <h1>Хајде да <em>разговарамо</em></h1>
                <p class="page-lead">Имате питање о еБиблиотеци, желите да повежете своју школу или тражите решење за библиотеку? Пишите нам. Прави људи из Академије Филиповић одговориће вам у најкраћем року.</p>
            </div>
            <div class="page-hero-aside">
                <div class="hero-stat-card hero-stat-card--question" style="background:var(--gold);color:var(--ink)" aria-hidden="true">
                    <svg xmlns="http://www.w3.org/2000/svg" width="52" height="52" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="16" x="2" y="4" rx="2"></rect><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"></path></svg>
                </div>
            </div>
        </div>
    </section>

    <section class="section section--tint">
        <div class="container contact-grid">
            <aside class="contact-info">
                <span class="section-kicker">О АКАДЕМИЈИ</span>
                <h2>Академија <em>Филиповић</em></h2>
                <p>Академија Филиповић више од две деценије развија решења за савремено образовање. еБиблиотека је њихов простор за једноставнији, доступнији и савременији рад школских библиотека.</p>
                <div class="contact-facts">
                    <a href="https://akademijafilipovic.com" target="_blank" rel="noreferrer"><span>ВЕБ</span><strong>akademijafilipovic.com →</strong></a>
                    <a href="mailto:akademijafilipovic@gmail.com"><span>Е-МАИЛ</span><strong>akademijafilipovic@gmail.com</strong></a>
                </div>
            </aside>

            <div class="contact-form-card">
                <span class="section-kicker">КОНТАКТ ФОРМА</span>
                <h2>Да ли имате <em>питање за нас?</em></h2>

                @if (session('contact_sent'))
                    <div class="contact-success" role="status">Порука је послата. Хвала вам што сте нам се јавили.</div>
                @endif

                <form action="{{ route('contact.store') }}" method="post">
                    @csrf
                    <div class="contact-form-grid">
                        <div class="contact-field">
                            <label for="contact-name">Име и презиме</label>
                            <input id="contact-name" name="name" value="{{ old('name') }}" required autocomplete="name">
                            @error('name')<small class="contact-error">{{ $message }}</small>@enderror
                        </div>
                        <div class="contact-field">
                            <label for="contact-email">Е-адреса</label>
                            <input id="contact-email" name="email" type="email" value="{{ old('email') }}" required autocomplete="email">
                            @error('email')<small class="contact-error">{{ $message }}</small>@enderror
                        </div>
                    </div>
                    <div class="contact-field">
                        <label for="contact-organization">Установа или организација <span>(опционо)</span></label>
                        <input id="contact-organization" name="organization" value="{{ old('organization') }}" autocomplete="organization">
                        @error('organization')<small class="contact-error">{{ $message }}</small>@enderror
                    </div>
                    <div class="contact-field">
                        <label for="contact-message">Ваша порука</label>
                        <textarea id="contact-message" name="message" rows="6" required>{{ old('message') }}</textarea>
                        @error('message')<small class="contact-error">{{ $message }}</small>@enderror
                    </div>
                    <button class="btn" type="submit">Пошаљите поруку →</button>
                </form>
            </div>
        </div>
    </section>

    @include('themes.ref.public.partials.footer')
</main>
@endsection
