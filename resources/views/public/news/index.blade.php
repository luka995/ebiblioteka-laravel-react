@extends('layouts.public')

@section('title', 'Новости | еБиблиотека')

@section('content')
<div class="public-shell catalog-shell" id="top">
    @include('public.partials.nav')

    <main>
        <section class="public-wrap news-detail-hero">
            <div class="breadcrumbs"><a href="{{ route('home', [], false) }}">Почетна</a><span>/</span><strong>Новости</strong></div>
            <span class="section-label">ИЗ НАШЕ ЗАЈЕДНИЦЕ</span>
            <h1 class="display">Новости</h1>
        </section>

        <section class="public-wrap news-section">
            <div class="news-grid">
                @forelse ($news as $index => $item)
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
                @empty
                    <p class="news-empty">Још увек нема објављених новости.</p>
                @endforelse
            </div>
        </section>
    </main>

    @include('public.partials.footer')
</div>
@endsection
