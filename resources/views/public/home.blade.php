@extends('layouts.public')

@section('title', 'Temukan properti untuk langkah berikutnya')
@section('description', 'Lihat rumah, tanah, dan bangunan kos yang tersedia dari katalog agensi.')

@section('content')
    <section class="bg-stone-900 px-5 py-16 text-white md:py-24">
        <div class="mx-auto max-w-7xl space-y-6">
            <p class="text-sm font-semibold uppercase tracking-[0.2em] text-emerald-300">Rumah · Tanah · Kos</p>
            <h1 class="max-w-3xl text-4xl font-semibold leading-tight md:text-6xl">Temukan properti yang sesuai dengan rencana Anda.</h1>
            <p class="max-w-xl text-lg text-stone-300">Jelajahi pilihan yang tersedia dan lihat informasi terbarunya pada halaman detail.</p>
            <a href="{{ route('properties.index') }}" class="inline-flex rounded-lg bg-emerald-400 px-6 py-3 font-semibold text-stone-950 hover:bg-emerald-300">Lihat semua properti</a>
        </div>
    </section>
    <section class="mx-auto max-w-7xl space-y-7 px-5 py-12 md:px-8">
        <div><h2 class="text-3xl font-semibold">Pilihan terbaru</h2><p class="mt-2 text-stone-600">Properti yang saat ini tersedia.</p></div>
        @if ($listings->isEmpty())
            <p class="rounded-xl border border-stone-200 bg-white p-8 text-stone-600">Belum ada properti tersedia. Silakan kembali lagi nanti.</p>
        @else
            <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($listings as $listing)
                    <x-listing-card :listing="$listing" />
                @endforeach
            </div>
        @endif
    </section>
@endsection
