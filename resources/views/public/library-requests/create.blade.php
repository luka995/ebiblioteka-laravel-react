@extends('layouts.public')

@section('title', 'Регистрација школе | еБиблиотека')
@section('meta_description', 'Региструјте своју школу за еБиблиотеку и затражите отварање дигиталне библиотеке. Академија Филиповић вас контактира након пријема захтева.')

@section('content')
<div class="public-shell catalog-shell contact-shell request-shell" id="top">
    @include('public.partials.nav')

    <main>
        <section class="contact-hero public-wrap">
            <div>
                <div class="breadcrumbs"><a href="{{ route('home', [], false) }}">Почетна</a><span>/</span><strong>Регистрација школе</strong></div>
                <span class="section-label">ЗАХТЕВ ЗА ОТВАРАЊЕ БИБЛИОТЕКЕ</span>
                <h1 class="display">Отворите<br><span class="serif">своју библиотеку.</span></h1>
                <p>Попуните податке о школи и пошаљите захтев. Академија Филиповић ће вас контактирати, припремити приступ и помоћи око првих корака.</p>
            </div>
            <div class="contact-hero-note">
                <span class="contact-note-mark">✦</span>
                <strong>Ваша<br>школа.<br>Ваша прича.</strong>
                <small>АКАДЕМИЈА ФИЛИПОВИЋ<br>еБИБЛИОТЕКА</small>
            </div>
        </section>

        <section class="contact-section public-wrap">
            <aside class="contact-info-card request-info-card">
                <span class="section-label">ШТА СЛЕДИ</span>
                <h2 class="display">Три корака<br><span class="serif">до приступа.</span></h2>
                <ol class="request-steps">
                    <li><span>01</span><div><strong>Пошаљите захтев</strong><p>Попуните податке о школи и контакт особи.</p></div></li>
                    <li><span>02</span><div><strong>Потврда</strong><p>Академија Филиповић вас контактира и договора прве кораке.</p></div></li>
                    <li><span>03</span><div><strong>Академија Филиповић креира библиотеку и приступ библиотеци</strong><p>Добијате приступ, уносите фонд и почињете са радом.</p></div></li>
                </ol>
                <div class="contact-facts">
                    <a href="tel:{{ config('public_site.contact.phone_href') }}"><span>ТЕЛЕФОН</span><strong>{{ config('public_site.contact.phone') }}</strong></a>
                    <a href="mailto:{{ config('public_site.contact.email') }}"><span>Е-ПОШТА</span><strong>{{ config('public_site.contact.email') }}</strong></a>
                    <a href="{{ config('public_site.contact.website') }}" target="_blank" rel="noreferrer"><span>ВЕБ</span><strong>{{ config('public_site.contact.website_label') }} ↗</strong></a>
                </div>
                <div class="contact-info-footer"><span class="contact-info-dot"></span><span>За цену и услове посебно нам пишите.</span></div>
            </aside>

            <div class="contact-form-card">
                <span class="section-label">ПОДАЦИ О ШКОЛИ</span>
                <h2 class="display">Региструјте<br><span class="serif">школу.</span></h2>

                @php
                    $requestFlash = session('library_request_sent')
                        ? ['title' => 'Захтев је послат', 'message' => 'Хвала вам. Академија Филиповић ће вас контактирати у најкраћем року.', 'tone' => 'success']
                        : (session('library_request_error')
                            ? ['title' => 'Дошло је до грешке', 'message' => session('library_request_error'), 'tone' => 'error']
                            : null);
                @endphp

                @if ($requestFlash)
                    <dialog class="contact-modal contact-modal--{{ $requestFlash['tone'] }}" data-flash-modal role="dialog" aria-labelledby="request-modal-title">
                        <div class="contact-modal-body">
                            <h3 id="request-modal-title">{{ $requestFlash['title'] }}</h3>
                            <p>{{ $requestFlash['message'] }}</p>
                            <button class="contact-modal-close" data-flash-modal-close type="button">У реду</button>
                        </div>
                    </dialog>
                @endif

                <form action="{{ route('library-requests.store', [], false) }}" method="post">
                    @csrf

                    <div class="contact-field">
                        <label for="request-school-name">Назив школе</label>
                        <input id="request-school-name" name="school_name" value="{{ old('school_name') }}" required autocomplete="organization">
                        @error('school_name')<small class="contact-error">{{ $message }}</small>@enderror
                    </div>

                    <div class="contact-form-grid">
                        <div class="contact-field">
                            <label for="request-school-type">Тип школе</label>
                            <select id="request-school-type" name="school_type" required>
                                <option value="">Изаберите тип школе</option>
                                @foreach (config('public_site.school_types') as $value => $label)
                                    <option value="{{ $value }}" @selected(old('school_type') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('school_type')<small class="contact-error">{{ $message }}</small>@enderror
                        </div>
                        <div class="contact-field">
                            <label for="request-city">Место</label>
                            <input id="request-city" name="city" value="{{ old('city') }}" required autocomplete="address-level2">
                            @error('city')<small class="contact-error">{{ $message }}</small>@enderror
                        </div>
                    </div>

                    <div class="contact-field">
                        <label for="request-address">Адреса школе</label>
                        <input id="request-address" name="address" value="{{ old('address') }}" required autocomplete="street-address">
                        @error('address')<small class="contact-error">{{ $message }}</small>@enderror
                    </div>

                    <div class="contact-form-grid">
                        <div class="contact-field">
                            <label for="request-contact-name">Контакт особа</label>
                            <input id="request-contact-name" name="contact_name" value="{{ old('contact_name') }}" required autocomplete="name">
                            @error('contact_name')<small class="contact-error">{{ $message }}</small>@enderror
                        </div>
                        <div class="contact-field">
                            <label for="request-phone">Телефон</label>
                            <input id="request-phone" name="contact_phone" type="tel" value="{{ old('contact_phone') }}" required autocomplete="tel">
                            @error('contact_phone')<small class="contact-error">{{ $message }}</small>@enderror
                        </div>
                    </div>

                    <div class="contact-field">
                        <label for="request-email">Е-маил адреса</label>
                        <input id="request-email" name="contact_email" type="email" value="{{ old('contact_email') }}" required autocomplete="email">
                        @error('contact_email')<small class="contact-error">{{ $message }}</small>@enderror
                    </div>

                    <div class="contact-form-grid">
                        <div class="contact-field">
                            <label for="request-students">Број ученика <span>(опционо)</span></label>
                            <input id="request-students" name="student_count" type="number" min="0" max="20000" value="{{ old('student_count') }}" inputmode="numeric">
                            @error('student_count')<small class="contact-error">{{ $message }}</small>@enderror
                        </div>
                        <div class="contact-field">
                            <label for="request-employees">Број запослених <span>(опционо)</span></label>
                            <input id="request-employees" name="employee_count" type="number" min="0" max="5000" value="{{ old('employee_count') }}" inputmode="numeric">
                            @error('employee_count')<small class="contact-error">{{ $message }}</small>@enderror
                        </div>
                    </div>

                    <div class="contact-field">
                        <label for="request-message">Порука <span>(опционо)</span></label>
                        <textarea id="request-message" name="message" rows="5" placeholder="Реците нам нешто о библиотеци, фонду или плановима.">{{ old('message') }}</textarea>
                        @error('message')<small class="contact-error">{{ $message }}</small>@enderror
                    </div>

                    <input type="hidden" name="turnstile_token" id="library-request-turnstile-token">
                    <div id="library-request-turnstile" class="contact-turnstile" data-sitekey="{{ config('services.turnstile.site_key') }}" data-action="{{ config('services.turnstile.library_request_action') }}"></div>
                    <button class="button button-blue" type="submit" data-library-request-submit @if (config('services.turnstile.site_key')) disabled @endif>Пошаљите захтев <span>↗</span></button>
                    <small class="request-note">Слањем захтева прихватате да вас контактирамо у вези са отварањем библиотеке.</small>
                </form>
            </div>
        </section>
    </main>

    @include('public.partials.footer')
</div>
@endsection

@push('scripts')
    @if (config('services.turnstile.site_key'))
        <script src="https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit&onload=onLibraryRequestTurnstileLoad" async defer></script>
        <script>
            window.onLibraryRequestTurnstileLoad = function () {
                const container = document.getElementById('library-request-turnstile')
                const tokenInput = document.getElementById('library-request-turnstile-token')
                const submitButton = document.querySelector('[data-library-request-submit]')

                if (!container || !window.turnstile || !submitButton) {
                    return
                }

                const setToken = (token) => {
                    tokenInput.value = token
                    submitButton.disabled = !token
                }

                window.turnstile.render(container, {
                    sitekey: container.dataset.sitekey,
                    action: container.dataset.action,
                    callback: setToken,
                    'expired-callback': () => setToken(''),
                    'error-callback': () => setToken(''),
                })
            }
        </script>
    @endif
@endpush
