@extends('layouts.public')

@section('title', \App\Support\Text::cyr($news->title).' | еБиблиотека')

@section('content')
<div class="public-shell catalog-shell" id="top">
    @include('public.partials.nav')

    <main>
        <section class="public-wrap news-detail-hero">
            <div class="breadcrumbs"><a href="{{ route('home', [], false) }}">Почетна</a><span>/</span><a href="{{ route('news.index', [], false) }}">Новости</a><span>/</span><strong>{{ \App\Support\Text::cyr($news->title) }}</strong></div>
            <span class="section-label">ИЗ НАШЕ ЗАЈЕДНИЦЕ</span>
            <h1 class="display">{{ \App\Support\Text::cyr($news->title) }}</h1>
            <p class="news-detail-date">{{ $news->date->format('d.m.Y.') }}</p>
        </section>

        <section class="public-wrap news-detail-body">
            @if ($news->image_url)
                <figure class="news-detail-cover">
                    <button class="news-cover-zoom" type="button" data-news-lightbox-trigger aria-label="Увећај слику">
                        <img src="{{ $news->image_url }}" alt="{{ \App\Support\Text::cyr($news->title) }}">
                        <span class="news-cover-zoom-hint">⌕ Увећај</span>
                    </button>
                </figure>
                <dialog class="news-lightbox" data-news-lightbox aria-label="Увећана слика">
                    <img src="{{ $news->image_url }}" alt="{{ \App\Support\Text::cyr($news->title) }}">
                    <button class="news-lightbox-close" type="button" data-news-lightbox-close aria-label="Затвори">✕</button>
                </dialog>
            @endif
            <div class="news-prose">{!! $news->body !!}</div>
        </section>

        @if ($other->isNotEmpty())
            <section class="public-wrap news-more-section">
                <div class="section-heading"><div><span class="section-label">ЈОШ НОВОСТИ</span><h2 class="display">Читајте <span class="serif">даље.</span></h2></div></div>
                <div class="news-grid">
                    @foreach ($other as $index => $item)
                        <article class="news-card {{ $index === 0 ? 'news-card-featured' : '' }}">
                            @if ($item->image_url)
                                <div class="news-thumb"><img src="{{ $item->image_url }}" alt="" loading="lazy"></div>
                            @endif
                            <div class="news-body">
                                <div class="news-top"><span>НОВОСТ</span><span>{{ $item->date->format('d.m.Y.') }}</span></div>
                                <h3><a href="{{ route('news.show', $item->slug, false) }}">{{ \App\Support\Text::cyr($item->title) }}</a></h3>
                            </div>
                            <a class="news-more" href="{{ route('news.show', $item->slug, false) }}" aria-label="Прочитајте: {{ \App\Support\Text::cyr($item->title) }}">↗</a>
                        </article>
                    @endforeach
                </div>
            </section>
        @endif
    </main>

    @include('public.partials.footer')
</div>
@endsection
