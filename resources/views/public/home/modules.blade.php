<section class="modules-section" id="resenje">
    <div class="public-wrap modules-inner">
        <div class="modules-heading">
            <div>
                <span class="section-label">СВЕ НА ЈЕДНОМ МЕСТУ</span>
                <h2 class="display">Модули који покривају <span class="serif">цео рад библиотеке.</span></h2>
            </div>
            <p>Од првог уноса наслова до годишњег извештаја — свакодневни посао библиотеке у једном систему.</p>
        </div>
        <div class="module-grid">
            @foreach ($modules as $index => $module)
                <article class="module-card">
                    <div class="module-top">
                        <span class="module-number">0{{ $index + 1 }}</span>
                        <span class="module-icon module-icon-{{ $module['icon'] }}" aria-hidden="true">
                            @switch($module['icon'])
                                @case('catalog')
                                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 7v14"></path><path d="M3 18a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1h5a4 4 0 0 1 4 4 4 4 0 0 1 4-4h5a1 1 0 0 1 1 1v13a1 1 0 0 1-1 1h-6a3 3 0 0 0-3 3 3 3 0 0 0-3-3z"></path></svg>
                                    @break
                                @case('loans')
                                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 3 4 7l4 4"></path><path d="M4 7h16"></path><path d="m16 21 4-4-4-4"></path><path d="M20 17H4"></path></svg>
                                    @break
                                @case('reservations')
                                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"></path><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"></path></svg>
                                    @break
                                @case('reports')
                                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><path d="M14 2v6h6"></path><path d="M8 13h8"></path><path d="M8 17h5"></path></svg>
                                    @break
                                @case('barcode')
                                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 5v14"></path><path d="M8 5v14"></path><path d="M12 5v14"></path><path d="M17 5v14"></path><path d="M21 5v14"></path></svg>
                                    @break
                                @default
                                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M22 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                            @endswitch
                        </span>
                    </div>
                    <h3>{{ $module['title'] }}</h3>
                    <p>{{ $module['text'] }}</p>
                </article>
            @endforeach
        </div>
        <div class="modules-footer">
            <a class="text-link" href="{{ route('project.about', [], false) }}">Сазнајте више о пројекту <span>↗</span></a>
        </div>
    </div>
</section>
