<!doctype html>
<html lang="sr-Cyrl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#83d2eb">
    <meta name="description" content="@yield('meta_description', 'еБиблиотека је софтвер за школске библиотеке: каталог, позајмице, резервације, инвентар, извештаји и чланске карте.')">
    <link rel="canonical" href="{{ url()->current() }}">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="еБиблиотека">
    <meta property="og:title" content="@yield('title', 'еБиблиотека | Софтвер за школске библиотеке')">
    <meta property="og:description" content="@yield('meta_description', 'еБиблиотека је софтвер за школске библиотеке: каталог, позајмице, резервације, инвентар, извештаји и чланске карте.')">
    <meta property="og:url" content="{{ url()->current() }}">
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="alternate icon" href="{{ asset('favicon.svg') }}">
    <title>@yield('title', 'еБиблиотека | Софтвер за школске библиотеке')</title>
    @stack('head')
    @vite(['resources/css/app.css', 'resources/js/app.ts'])
</head>
<body>
    @yield('content')
    @stack('scripts')
</body>
</html>
