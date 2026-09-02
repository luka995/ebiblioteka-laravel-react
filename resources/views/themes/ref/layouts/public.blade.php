<!doctype html>
<html lang="sr-Cyrl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#102b46">
    <meta name="description" content="Ебиблиотека Академије Филиповић — савремена електронска библиотека за школе, библиотекаре, наставнике и ученике.">
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="alternate icon" href="{{ asset('favicon.svg') }}">
    <title>@yield('title', 'Ебиблиотека Академије Филиповић')</title>
    @vite(['resources/css/reference.css', 'resources/js/app.ts'])
</head>
<body>
    @yield('content')
</body>
</html>
