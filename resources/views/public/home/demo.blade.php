<section class="demo-section" id="demo">
    <div class="public-wrap demo-inner">
        <div class="demo-heading">
            <div>
                <span class="section-label">ДЕМО УЖИВО</span>
                <h2 class="display">Испробајте еБиблиотеку <span class="serif">из праве улоге.</span></h2>
            </div>
            <p>Погледајте како изгледа рад администратора, библиотекара и корисника. Уђите у демо и истражите без обавеза.</p>
        </div>

        <div class="demo-widget" data-demo-widget>
            <div class="demo-tabs" role="tablist" aria-label="Улоге у демо верзији">
                @foreach ($demo['roles'] as $index => $role)
                    <button class="demo-tab" type="button" role="tab" id="demo-tab-{{ $role['key'] }}" aria-controls="demo-panel-{{ $role['key'] }}" aria-selected="{{ $index === 0 ? 'true' : 'false' }}" tabindex="{{ $index === 0 ? '0' : '-1' }}" data-demo-tab="{{ $role['key'] }}">{{ $role['label'] }}</button>
                @endforeach
            </div>

            <div class="demo-panel-wrap">
                @foreach ($demo['roles'] as $index => $role)
                    <div class="demo-panel" id="demo-panel-{{ $role['key'] }}" role="tabpanel" aria-labelledby="demo-tab-{{ $role['key'] }}" data-demo-panel="{{ $role['key'] }}" @if ($index !== 0) hidden @endif>
                        <div class="demo-panel-main">
                            <h3>{{ $role['tagline'] }}</h3>
                            <ul class="demo-perks">
                                @foreach ($role['perks'] as $perk)
                                    <li>{{ $perk }}</li>
                                @endforeach
                            </ul>
                        </div>
                        <div class="demo-access">
                            <span class="demo-access-label">ПРИСТУПНИ ПОДАЦИ</span>
                            @if ($demo['show_credentials'])
                                <div class="demo-credential"><span>Корисничко име</span><code>{{ $role['email'] }}</code></div>
                                <div class="demo-credential"><span>Лозинка</span><code>{{ $role['password'] }}</code></div>
                            @else
                                <p class="demo-note">Демо налози се припремају. За приступ нам се обратите.</p>
                            @endif
                            <a class="button button-blue" href="{{ \App\Support\FrontendUrl::url() }}">Отвори демо <span>↗</span></a>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</section>
