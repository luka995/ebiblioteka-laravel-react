@extends('layouts.public')

@section('title', 'Библиотеке | еБиблиотека')

@section('content')
<div class="public-shell catalog-shell" id="top">
    @include('public.partials.nav')
    <main>
        <section class="catalog-hero public-wrap">
            <div><div class="breadcrumbs"><a href="{{ route('home', [], false) }}">Почетна</a><span>/</span><strong>Библиотеке</strong></div><span class="section-label">МРЕЖА БИБЛИОТЕКА</span><h1 class="display">Пронађите библиотеку<br><span class="serif">за себе.</span></h1><p>Претражите библиотеке, отворите њихове категорије и пронађите књигу која чека на вас.</p></div>
            <div class="catalog-doodle" aria-hidden="true"><span>✦</span><strong>листај<br>радознало</strong><i></i></div>
        </section>
        <section class="public-wrap library-search-panel">
            <div><span class="section-label">ИСТРАЖИТЕ КОЛЕКЦИЈЕ</span><h2 class="display">Коју полицу<br><span class="serif">отварамо?</span></h2></div>
            <form class="catalog-search" action="{{ route('libraries.index', [], false) }}" method="get"><label for="library-search">Назив библиотеке, град или адреса</label><div><span>⌕</span><input id="library-search" name="q" value="{{ $query }}" placeholder="На пример: Нови Сад" type="search"><button type="submit">Претражи <span>↗</span></button></div></form>
        </section>
        <section class="public-wrap catalog-results">
            <div class="results-heading"><div><span class="section-label">{{ $libraries->count() }} {{ $libraries->count() === 1 ? 'БИБЛИОТЕКА' : 'БИБЛИОТЕКЕ' }}</span><h2 class="display">Библиотеке које<br><span class="serif">можете посетити.</span></h2></div><p>Изаберите библиотеку да видите њене категорије и књиге.</p></div>
            @if ($libraries->isEmpty())
                <div class="empty-catalog"><span>⌕</span><h3>Нисмо пронашли ту библиотеку.</h3><p>Покушајте са другим називом или градом.</p><a class="button button-blue" href="{{ route('libraries.index', [], false) }}">Прикажи све библиотеке</a></div>
            @else
                <div class="library-directory">
                    @foreach ($libraries as $index => $library)
                        <a class="directory-card" href="{{ route('libraries.show', $library['slug'], false) }}"><span class="directory-number">0{{ $index + 1 }}</span><span class="directory-emblem" style="background: {{ $library['color'] }}">{{ $library['mark'] }}</span><span class="directory-copy"><strong>{{ \App\Support\Text::cyr($library['name']) }}</strong><small>{{ \App\Support\Text::cyr($library['city']) }} · {{ \App\Support\Text::cyr($library['address']) }}</small><span>{{ count($library['categories']) }} категорија · {{ \App\Support\Text::cyr($library['count']) }}</span></span><span class="directory-arrow">↗</span></a>
                    @endforeach
                </div>
            @endif
        </section>
    </main>
    @include('public.partials.footer')
</div>
@endsection
