<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="@yield('description', 'Harga minimalis, kualitas maksimalis. Jelajahi properti yang tersedia di '.config('app.name').'.')">
    <meta property="og:title" content="@yield('title', 'Katalog Properti')">
    <meta property="og:description" content="@yield('description', 'Harga minimalis, kualitas maksimalis. Jelajahi properti yang tersedia di '.config('app.name').'.')">
    @hasSection('robots')
        <meta name="robots" content="@yield('robots')">
    @endif
    <meta property="og:image" content="@yield('image', asset('images/og-default.jpg'))">
    <title>@yield('title', 'Katalog Properti') · {{ config('app.name') }}</title>
    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen overflow-x-hidden bg-white text-stone-950 antialiased">
    <header class="sticky top-0 z-30 border-b border-stone-200/70 bg-white/90 backdrop-blur">
        <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-4 md:px-8">
            <a href="{{ route('home') }}" class="flex min-w-0 items-center gap-2 text-base font-semibold tracking-tight"><x-app-logo-icon class="h-8 w-auto shrink-0" aria-hidden="true" /><span class="truncate">{{ config('app.name') }}</span></a>
            <nav aria-label="Navigasi utama" class="hidden items-center gap-8 text-sm text-stone-600 md:flex">
                <a href="{{ route('properties.index') }}" class="hover:text-stone-950">Properti</a>
                <a href="{{ route('home') }}#cara-kerja" class="hover:text-stone-950">Cara kerja</a>
                <a href="{{ route('home') }}#tim-sales" class="hover:text-stone-950">Tim sales</a>
                <a href="{{ route('home') }}#faq" class="hover:text-stone-950">FAQ</a>
            </nav>
            <a href="{{ route('properties.index') }}" class="shrink-0 whitespace-nowrap rounded-full border border-stone-300 px-4 py-2 text-sm font-medium hover:border-stone-950">Cari properti</a>
        </div>
    </header>
    <main>@yield('content')</main>
    @if (config('assistant.enabled') && app()->environment(['local', 'testing']))
        <x-assistant-chat />
    @endif
    <footer class="mt-24 border-t border-stone-200">
        <div class="mx-auto grid max-w-7xl gap-10 px-4 py-12 md:grid-cols-3 md:px-8">
            <div class="space-y-2">
                <p class="font-semibold tracking-tight">{{ config('app.name') }}</p>
                <p class="max-w-xs text-sm text-stone-500">Harga minimalis, kualitas maksimalis. Rumah, tanah, dan bangunan kos dalam satu katalog.</p>
            </div>
            <nav aria-label="Navigasi footer" class="flex flex-col gap-2 text-sm text-stone-600">
                <a href="{{ route('properties.index') }}" class="hover:text-stone-950">Semua properti</a>
                <a href="{{ route('home') }}#cara-kerja" class="hover:text-stone-950">Cara kerja</a>
                <a href="{{ route('home') }}#tim-sales" class="hover:text-stone-950">Tim sales</a>
                <a href="{{ route('home') }}#faq" class="hover:text-stone-950">FAQ</a>            </nav>
            <p class="text-sm text-stone-500">Informasi diperbarui oleh tim kami. Harga dan ketersediaan dapat berubah sewaktu-waktu.</p>
        </div>
        <div class="mx-auto flex max-w-7xl justify-between border-t border-stone-200 px-4 py-6 text-xs text-stone-500 md:px-8">
            <span>© {{ date('Y') }} {{ config('app.name') }}</span>
            <span>Katalog properti</span>
        </div>
        <p aria-hidden="true" class="select-none overflow-hidden whitespace-nowrap text-center text-[7vw] font-semibold leading-none tracking-tighter text-stone-100">{{ config('app.name') }}</p>
    </footer>
</body>
</html>
