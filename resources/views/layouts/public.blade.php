<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="@yield('description', 'Jelajahi properti yang tersedia dari satu katalog agensi.')">
    <meta property="og:title" content="@yield('title', 'Katalog Properti')">
    <meta property="og:description" content="@yield('description', 'Jelajahi properti yang tersedia dari satu katalog agensi.')">
    @hasSection('image')
        <meta property="og:image" content="@yield('image')">
    @endif
    <title>@yield('title', 'Katalog Properti') · {{ config('app.name') }}</title>
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen overflow-x-hidden bg-white text-stone-950 antialiased">
    <header class="sticky top-0 z-30 border-b border-stone-200/70 bg-white/90 backdrop-blur">
        <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-4 md:px-8">
            <a href="{{ route('home') }}" class="min-w-0 truncate text-base font-semibold tracking-tight">{{ config('app.name') }}</a>
            <nav aria-label="Navigasi utama" class="hidden items-center gap-8 text-sm text-stone-600 md:flex">
                <a href="{{ route('properties.index') }}" class="hover:text-stone-950">Properti</a>
                <a href="{{ route('home') }}#cara-kerja" class="hover:text-stone-950">Cara kerja</a>
                <a href="{{ route('home') }}#tim-sales" class="hover:text-stone-950">Tim sales</a>
                <a href="{{ route('home') }}#faq" class="hover:text-stone-950">FAQ</a>
            </nav>
            <div class="flex shrink-0 items-center gap-4 whitespace-nowrap text-sm">
                @auth
                    <a href="{{ route('dashboard') }}" class="hidden text-stone-600 hover:text-stone-950 sm:inline">Area staf</a>
                @else
                    <a href="{{ route('login') }}" class="hidden text-stone-600 hover:text-stone-950 sm:inline">Masuk staf</a>
                @endauth
                <a href="{{ route('properties.index') }}" class="rounded-full border border-stone-300 px-4 py-2 font-medium hover:border-stone-950">Cari properti</a>
            </div>
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
                <p class="max-w-xs text-sm text-stone-500">Satu katalog untuk rumah, tanah, dan bangunan kos dari agensi kami.</p>
            </div>
            <nav aria-label="Navigasi footer" class="flex flex-col gap-2 text-sm text-stone-600">
                <a href="{{ route('properties.index') }}" class="hover:text-stone-950">Semua properti</a>
                <a href="{{ route('home') }}#cara-kerja" class="hover:text-stone-950">Cara kerja</a>
                <a href="{{ route('home') }}#tim-sales" class="hover:text-stone-950">Tim sales</a>
                <a href="{{ route('home') }}#faq" class="hover:text-stone-950">FAQ</a>
                <a href="{{ auth()->check() ? route('dashboard') : route('login') }}" class="hover:text-stone-950">{{ auth()->check() ? 'Area staf' : 'Masuk staf' }}</a>
            </nav>
            <p class="text-sm text-stone-500">Informasi diperbarui oleh agensi. Harga dan ketersediaan dapat berubah sewaktu-waktu.</p>
        </div>
        <div class="mx-auto flex max-w-7xl justify-between border-t border-stone-200 px-4 py-6 text-xs text-stone-500 md:px-8">
            <span>© {{ date('Y') }} {{ config('app.name') }}</span>
            <span>Katalog properti</span>
        </div>
        <p aria-hidden="true" class="select-none overflow-hidden whitespace-nowrap text-center text-[7vw] font-semibold leading-none tracking-tighter text-stone-100">{{ config('app.name') }}</p>
    </footer>
</body>
</html>
