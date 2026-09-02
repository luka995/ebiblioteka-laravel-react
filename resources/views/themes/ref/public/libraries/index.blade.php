@extends('themes.ref.layouts.public')

@section('title', 'Каталог књига | Ебиблиотека Академије Филиповић')

@section('content')
@php $markers = ['#276f8f', '#d7a344', '#a94843', '#497768', '#1d3859']; @endphp

<main>
    @include('themes.ref.public.partials.nav')

    <section class="page-hero">
        <div class="container page-hero-inner">
            <div>
                <div class="crumbs"><a href="{{ route('home') }}">Почетна</a><span>/</span><span class="current">Каталог књига</span></div>
                <span class="section-kicker">МРЕЖА БИБЛИОТЕКА</span>
                <h1>Пронађите књигу <em>за себе</em></h1>
                <p class="page-lead">Претражите библиотеке, отворите њихове категорије и пронађите наслов који чека на вас — по наслову, аутору, граду или адреси.</p>
            </div>
            <div class="page-hero-aside">
                <div class="hero-stat-card">
                    <small>Библиотеке у мрежи</small>
                    <strong>{{ $libraries->count() }}</strong>
                    <span>и расту сваког месеца</span>
                </div>
            </div>
        </div>
    </section>

    <section class="section">
        <div class="container">
            <div class="search-panel">
                <h2>Претражите библиотеке</h2>
                <form class="search-box" action="{{ route('libraries.index') }}" method="get">
                    <svg xmlns="http://www.w3.org/2000/svg" width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m21 21-4.34-4.34"></path><circle cx="11" cy="11" r="8"></circle></svg>
                    <input name="q" type="search" placeholder="Назив библиотеке, град или адреса" aria-label="Претрага библиотека" value="{{ $query }}">
                    <button type="submit">Претражи</button>
                </form>
            </div>
        </div>
    </section>

    <section class="section section--plain section--plain-bottom">
        <div class="container">
            <div class="lead-heading">
                <div>
                    <span class="section-kicker">{{ $libraries->count() }} {{ $libraries->count() === 1 ? 'БИБЛИОТЕКА' : 'БИБЛИОТЕКЕ' }}</span>
                    <h2>Библиотеке <em>које можете посетити</em></h2>
                </div>
                <p>Изаберите библиотеку да видите њене категорије и књиге.</p>
            </div>

            @if ($libraries->isEmpty())
                <div class="no-results">
                    <h3>Нисмо пронашли ту библиотеку.</h3>
                    <p>Покушајте са другим називом или градом.</p>
                    <p style="margin-top:18px"><a class="btn" href="{{ route('libraries.index') }}">Прикажи све библиотеке</a></p>
                </div>
            @else
                <div class="directory">
                    @foreach ($libraries as $index => $library)
                        <a class="directory-card" href="{{ route('libraries.show', $library['slug']) }}">
                            <span class="directory-index">0{{ $index + 1 }}</span>
                            <span class="directory-emblem {{ $index % 2 ? 'gold' : '' }}" style="background: {{ $markers[$index % count($markers)] }}">{{ $library['mark'] }}</span>
                            <span class="directory-copy">
                                <strong>{{ \App\Support\Text::cyr($library['name']) }}</strong>
                                <small>{{ \App\Support\Text::cyr($library['city']) }} · {{ \App\Support\Text::cyr($library['address']) }}</small>
                                <span>{{ count($library['categories']) }} категорија · {{ \App\Support\Text::cyr($library['count']) }}</span>
                            </span>
                            <span class="arrow-link">→</span>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
    </section>

    @include('themes.ref.public.partials.footer')
</main>
@endsection
