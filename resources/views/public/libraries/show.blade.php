@extends('layouts.public')

@section('title', \App\Support\Text::cyr($library['name']).' | еБиблиотека')

@section('content')
<div class="public-shell catalog-shell" id="top">
    @include('public.partials.nav')
    <main>
        <section class="public-wrap catalog-detail-hero"><div><div class="breadcrumbs"><a href="{{ route('home', [], false) }}">Почетна</a><span>/</span><a href="{{ route('libraries.index', [], false) }}">Библиотеке</a><span>/</span><strong>{{ \App\Support\Text::cyr($library['name']) }}</strong></div><span class="section-label">ДОБРО ДОШЛИ У БИБЛИОТЕКУ</span><h1 class="display">{{ \App\Support\Text::cyr($library['name']) }}</h1><p>{{ \App\Support\Text::cyr($library['description']) }}</p><div class="library-facts"><span><strong>АДРЕСА</strong>{{ \App\Support\Text::cyr($library['city']) }} · {{ \App\Support\Text::cyr($library['address']) }}</span><span><strong>РАДНО ВРЕМЕ</strong>{{ \App\Support\Text::cyr($library['work_time']) }}</span><span><strong>ФОНД</strong>{{ \App\Support\Text::cyr($library['count']) }}</span></div></div><div class="detail-emblem" style="background: {{ $library['color'] }}"><span>{{ $library['mark'] }}</span><small>{{ \App\Support\Text::cyr($library['city']) }}</small></div></section>
        <section class="public-wrap category-section"><div class="results-heading"><div><span class="section-label">КАТЕГОРИЈЕ У БИБЛИОТЕЦИ</span><h2 class="display">Шта желите да<br><span class="serif">читате данас?</span></h2></div><p>Изаберите категорију да прегледате наслове који су доступни у овој библиотеци.</p></div><div class="category-grid">@foreach ($library['categories'] as $index => $category)<a class="category-card" href="{{ route('libraries.category', [$library['slug'], $category['slug']], false) }}"><span class="category-number">0{{ $index + 1 }}</span><span class="category-icon" style="background: {{ $category['color'] }}">✦</span><strong>{{ \App\Support\Text::cyr($category['name']) }}</strong><p>{{ \App\Support\Text::cyr($category['description']) }}</p><span class="category-count">{{ count($category['books']) }} {{ count($category['books']) === 1 ? 'наслов' : 'наслова' }} <b>↗</b></span></a>@endforeach</div></section>
        <section class="public-wrap back-strip"><a class="text-link" href="{{ route('libraries.index', [], false) }}">← Назад на библиотеке</a><span>Свака библиотека има своју причу.</span></section>
    </main>
    @include('public.partials.footer')
</div>
@endsection
