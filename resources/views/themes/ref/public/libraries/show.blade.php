@extends('themes.ref.layouts.public')

@section('title', \App\Support\Text::cyr($library['name']).' | Ебиблиотека Академије Филиповић')

@section('content')
<main>
    @include('themes.ref.public.partials.nav')

    <section class="page-hero">
        <div class="container page-hero-inner">
            <div>
                <div class="crumbs">
                    <a href="{{ route('home') }}">Почетна</a><span>/</span>
                    <a href="{{ route('libraries.index') }}">Каталог књига</a><span>/</span>
                    <span class="current">{{ \App\Support\Text::cyr($library['name']) }}</span>
                </div>
                <span class="section-kicker">ДОБРО ДОШЛИ У БИБЛИОТЕКУ</span>
                <h1>{{ \App\Support\Text::cyr($library['name']) }}</h1>
                <p class="page-lead">{{ \App\Support\Text::cyr($library['description']) }}</p>
            </div>
            <div class="page-hero-aside">
                <div class="hero-stat-card">
                    <small>{{ \App\Support\Text::cyr($library['city']) }}</small>
                    <strong>{{ $library['mark'] }}</strong>
                    <span>{{ \App\Support\Text::cyr($library['count']) }}</span>
                </div>
            </div>
        </div>
    </section>

    <section class="section">
        <div class="container">
            <dl class="book-facts">
                <div><dt>Адреса</dt><dd>{{ \App\Support\Text::cyr($library['city']) }} · {{ \App\Support\Text::cyr($library['address']) }}</dd></div>
                <div><dt>Радно време</dt><dd>{{ \App\Support\Text::cyr($library['work_time']) }}</dd></div>
                <div><dt>Фонд</dt><dd>{{ \App\Support\Text::cyr($library['count']) }}</dd></div>
            </dl>
        </div>
    </section>

    <section class="section section--tint" style="padding-top:56px">
        <div class="container">
            <div class="lead-heading">
                <div>
                    <span class="section-kicker">КАТЕГОРИЈЕ У БИБЛИОТЕЦИ</span>
                    <h2>Шта желите да <em>читате данас?</em></h2>
                </div>
                <p>Изаберите категорију да прегледате наслове који су доступни у овој библиотеци.</p>
            </div>

            <div class="category-grid">
                @forelse ($library['categories'] as $index => $category)
                    <a class="category-card" href="{{ route('libraries.category', [$library['slug'], $category['slug']]) }}">
                        <div class="book-cover {{ ['cover-blue', 'cover-gold', 'cover-red', 'cover-green', 'cover-navy'][$index % 5] }}">
                            <span><i>{{ mb_strtoupper(\App\Support\Text::cyr($category['name'])) }}</i></span>
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 5v16"></path><path d="M20.001 19A2 2 0 0 0 22 17V5a2 2 0 0 0-1.999-2L16 3.002A5 5 0 0 0 12 5a5 5 0 0 0-4-2H4a2 2 0 0 0-2 2v12a2 2 0 0 0 1.999 2H8a5 5 0 0 1 4 2 5 5 0 0 1 4-2z"></path></svg>
                        </div>
                        <strong>{{ \App\Support\Text::cyr($category['name']) }}</strong>
                        <p>{{ \App\Support\Text::cyr($category['description']) }}</p>
                        <span>{{ count($category['books']) }} {{ count($category['books']) === 1 ? 'наслов' : 'наслова' }} · отвори →</span>
                    </a>
                @empty
                    <div class="no-results">
                        <h3>Још нема категорија у овој библиотеци.</h3>
                    </div>
                @endforelse
            </div>
        </div>
    </section>

    <section class="section section--plain">
        <div class="container back-strip">
            <a href="{{ route('libraries.index') }}">← Назад на библиотеке</a>
            <span>Свака библиотека има своју причу.</span>
        </div>
    </section>

    @include('themes.ref.public.partials.footer')
</main>
@endsection
