<!doctype html>
<html lang="sr-Latn">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#83d2eb">
    <meta name="description" content="eBiblioteka je digitalni prostor za učenike, roditelje, nastavnike i sve ljubitelje knjiga.">
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="alternate icon" href="{{ asset('favicon.svg') }}">
    <title>@yield('title', 'eBiblioteka | Svaka knjiga je nova pustolovina')</title>
    @vite(['resources/css/app.css', 'resources/js/app.ts'])
</head>
<body>
    @yield('content')
</body>
</html>
