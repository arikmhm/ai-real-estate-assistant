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
<body class="min-h-screen bg-stone-50 text-stone-900 antialiased">
    <header class="border-b border-stone-200 bg-white">
        <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-5 py-4 md:px-8">
            <a href="{{ route('home') }}" class="text-lg font-bold tracking-tight">Katalog Properti</a>
            <nav aria-label="Navigasi utama" class="flex items-center gap-4 text-sm font-medium">
                <a href="{{ route('properties.index') }}" class="hover:text-emerald-700">Semua properti</a>
                @auth
                    <a href="{{ route('dashboard') }}" class="hover:text-emerald-700">Area staf</a>
                @else
                    <a href="{{ route('login') }}" class="hover:text-emerald-700">Masuk staf</a>
                @endauth
            </nav>
        </div>
    </header>
    <main>@yield('content')</main>
    @if (config('assistant.enabled') && app()->environment(['local', 'testing']) && request()->routeIs('properties.index', 'properties.show'))
        <x-assistant-chat />
    @endif
    <footer class="mt-16 border-t border-stone-200 bg-white px-5 py-8 text-center text-sm text-stone-500">Katalog properti · Informasi diperbarui oleh agensi.</footer>
</body>
</html>
