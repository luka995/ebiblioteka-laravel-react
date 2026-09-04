<!doctype html>
<html lang="sr-Cyrl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#83d2eb">
    <meta name="description" content="еБиблиотека је дигитални простор за ученике, родитеље, наставнике и све љубитеље књига.">
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="alternate icon" href="{{ asset('favicon.svg') }}">
    <title>@yield('title', 'еБиблиотека | Свака књига је нова пустоловина')</title>
    @vite(['resources/css/app.css', 'resources/js/app.ts'])
</head>
<body>
    @yield('content')
</body>
</html>
