@extends('layouts.public')

@section('title', 'Контакт | еБиблиотека')

@section('content')
<div class="public-shell catalog-shell contact-shell" id="top">
    @include('public.partials.nav')

    <main>
        <section class="contact-hero public-wrap">
            <div>
                <div class="breadcrumbs"><a href="{{ route('home') }}">Почетна</a><span>/</span><strong>Контакт</strong></div>
                <span class="section-label">ЈАВИТЕ НАМ СЕ</span>
                <h1 class="display">Хајде да<br><span class="serif">разговарамо.</span></h1>
                <p>Имате питање о еБиблиотеци, желите да повежете своју школу или тражите решење за библиотеку? Пишите нам. Прави људи из Академије Филиповић одговориће вам у најкраћем року.</p>
            </div>
            <div class="contact-hero-note">
                <span class="contact-note-mark">✦</span>
                <strong>Отворени<br>за добру<br>идеју.</strong>
                <small>АКАДЕМИЈА ФИЛИПОВИЋ<br>еБИБЛИОТЕКА</small>
            </div>
        </section>

        <section class="contact-section public-wrap">
            <aside class="contact-info-card">
                <span class="section-label">О АКАДЕМИЈИ</span>
                <h2 class="display">Академија<br><span class="serif">Филиповић.</span></h2>
                <p>Академија Филиповић више од две деценије развија решења за савремено образовање. еБиблиотека је њихов простор за једноставнији, доступнији и савременији рад школских библиотека.</p>
                <div class="contact-facts">
                    <a href="https://akademijafilipovic.com" target="_blank" rel="noreferrer"><span>ВЕБ</span><strong>akademijafilipovic.com ↗</strong></a>
                    <a href="mailto:akademijafilipovic@gmail.com"><span>Е-ПОШТА</span><strong>akademijafilipovic@gmail.com</strong></a>
                </div>
                <div class="contact-info-footer"><span class="contact-info-dot"></span><span>Одговарамо на сваку поруку.</span></div>
            </aside>

            <div class="contact-form-card">
                <span class="section-label">КОНТАКТ ФОРМА</span>
                <h2 class="display">Да ли имате<br><span class="serif">питање за нас?</span></h2>

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
                            <label for="contact-email">Е-маил адреса</label>
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
                    <button class="button button-blue" type="submit">Пошаљите поруку <span>↗</span></button>
                </form>
            </div>
        </section>
    </main>

    @include('public.partials.footer')
</div>
@endsection
