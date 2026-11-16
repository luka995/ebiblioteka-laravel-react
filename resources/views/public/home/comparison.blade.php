<section class="public-wrap switch-section" id="zasto">
    <div class="switch-heading">
        <div>
            <span class="section-label">СА ПАПИРА И EXCELA</span>
            <h2 class="display">Мање администрације. <span class="serif">Више времена за књигу.</span></h2>
        </div>
        <p>Ручна евиденција успорава сваки корак. еБиблиотека преузима табеле, рокове и извештаје, а библиотекару оставља оно што је најважније — рад са читаоцима.</p>
    </div>
    <div class="switch-grid">
        <article class="switch-card switch-card-old">
            <span class="switch-badge">ДО САДА</span>
            <h3>Све се води ручно.</h3>
            <ul class="switch-list">
                @foreach ($comparison['before'] as $item)
                    <li><span aria-hidden="true">×</span>{{ $item }}</li>
                @endforeach
            </ul>
        </article>
        <article class="switch-card switch-card-new">
            <span class="switch-badge">СА еБИБЛИОТЕКОМ</span>
            <h3>Библиотека ради за вас.</h3>
            <ul class="switch-list">
                @foreach ($comparison['after'] as $item)
                    <li><span aria-hidden="true">✓</span>{{ $item }}</li>
                @endforeach
            </ul>
        </article>
    </div>
    <div class="switch-actions">
        <a class="button button-blue" href="{{ route('library-requests.create', [], false) }}">Пређите на дигитално <span>↗</span></a>
    </div>
</section>
