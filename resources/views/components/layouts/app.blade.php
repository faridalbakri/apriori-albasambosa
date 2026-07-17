<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
    <title>@yield('title', 'AlbaSambosa') — Frozen Food UMKM</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen flex flex-col">
    <x-navbar />
    <x-flash />

    <main class="flex-1">
        {{ $slot }}
    </main>

    <x-footer />

    <x-cookie-consent />

    @livewireScripts
</body>
</html>
