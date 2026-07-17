<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'AlbaSambosa') }}</title>
        <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-[var(--color-foreground)] antialiased">
        <div class="min-h-screen relative flex flex-col items-center justify-center bg-[var(--color-background)] px-4">
            {{-- Back link — top left --}}
            @props(['backLink' => null, 'backLabel' => null])

            <a href="{{ $backLink ?? route('catalog.index') }}" class="absolute top-4 left-4 inline-flex items-center gap-1 px-3 py-2 text-sm font-medium border border-border rounded-lg bg-white hover:bg-background hover:text-primary transition-colors duration-150 cursor-pointer shadow-sm">
                <x-heroicon-o-arrow-left class="w-4 h-4" />
                {{ $backLabel ?? 'Kembali ke Menu' }}
            </a>

            <div class="mb-6">
                <a href="{{ route('home') }}" class="text-3xl font-bold tracking-wide text-[var(--color-primary)] font-[family-name:var(--font-brand)]">
                    AlbaSambosa
                </a>
            </div>

            <div class="w-full sm:max-w-md bg-white border border-[var(--color-border)] rounded-xl shadow-sm p-6">
                {{ $slot }}
            </div>
        </div>
    </body>
</html>
