<section class="public-wrap news-section" id="novosti">
    <div class="section-heading"><div><span class="section-label">ИЗ НАШЕ ЗАЈЕДНИЦЕ</span><h2 class="display">Читајте између <span class="serif">редова.</span></h2></div><a class="text-link" href="{{ route('news.index', [], false) }}">Све новости <span>↗</span></a></div>
    <div class="news-grid">
        @foreach ($news as $index => $item)
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
