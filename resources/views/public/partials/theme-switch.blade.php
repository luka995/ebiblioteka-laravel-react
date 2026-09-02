@php
    $activeTheme = \App\Support\PublicTheme::active();
    $isLatin = ($themeSwitchLang ?? 'lat') === 'lat';
    $labelOne = $isLatin ? 'Tema 1' : 'Тема 1';
    $labelTwo = $isLatin ? 'Tema 2' : 'Тема 2';
    $labelGroup = $isLatin ? 'Izbor dizajna' : 'Избор дизајна';
@endphp
<nav class="theme-switch" role="group" aria-label="{{ $labelGroup }}">
    <a href="{{ request()->fullUrlWithQuery(['theme' => 'classic']) }}" class="{{ $activeTheme === 'classic' ? 'is-active' : '' }}">{{ $labelOne }}</a>
    <a href="{{ request()->fullUrlWithQuery(['theme' => 'ref']) }}" class="{{ $activeTheme === 'ref' ? 'is-active' : '' }}">{{ $labelTwo }}</a>
</nav>
