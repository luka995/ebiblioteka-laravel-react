<section class="public-wrap library-section" id="katalog">
    <div class="library-intro">
        <span class="section-label">ЈАВНИ КАТАЛОГ</span>
        <h2 class="display">Отворите библиотеку <span class="serif">и пронађите књигу.</span></h2>
        <p>Упознајте библиотеке које своје полице отварају дигитално. Пронађите место за себе, близу вас.</p>
        <a class="button button-outline" href="{{ route('libraries.index', [], false) }}">Прегледајте све библиотеке <span>↗</span></a>
    </div>
    <div class="library-list">
        @forelse ($libraries as $index => $library)
            <a class="library-item" href="{{ route('libraries.show', $library['slug'], false) }}"><span class="library-number">0{{ $index + 1 }}</span><span class="library-emblem" style="background: {{ $library['color'] }}">{{ $library['mark'] }}</span><span class="library-name"><strong>{{ \App\Support\Text::cyr($library['name']) }}</strong><small>{{ \App\Support\Text::cyr($library['city']) }}</small></span><span class="library-count">{{ \App\Support\Text::cyr($library['count']) }}</span><span class="library-arrow">↗</span></a>
        @empty
            <div class="empty-catalog"><span>⌕</span><h3>Прве библиотеке у мрежи ускоро.</h3><p>Региструјте школу и отворите своју библиотеку.</p><a class="button button-blue" href="{{ route('library-requests.create', [], false) }}">Региструјте школу</a></div>
        @endforelse
        @if ($libraryCount > 0)
            <div class="library-more">{{ $libraryCount }} {{ $libraryCount === 1 ? 'библиотека' : 'библиотеке' }} у мрежи · <a href="{{ route('libraries.index', [], false) }}">отворите каталог</a></div>
        @endif
    </div>
</section>
