@extends('layouts.public')

@section('title', 'Semua properti tersedia')
@section('description', 'Katalog rumah, tanah, dan bangunan kos yang diterbitkan dan tersedia.')

@section('content')
    <section class="mx-auto max-w-7xl space-y-7 px-5 py-12 md:px-8">
        <div class="space-y-2"><p class="text-sm font-semibold uppercase tracking-wider text-emerald-700">Katalog</p><h1 class="text-4xl font-semibold">Properti tersedia</h1><p class="text-stone-600">Pilih properti untuk melihat foto dan informasi lengkap.</p></div>
        @if ($listings->isEmpty())
            <div class="rounded-xl border border-stone-200 bg-white p-8"><h2 class="text-xl font-semibold">Belum ada properti tersedia</h2><p class="mt-2 text-stone-600">Listing baru akan muncul di sini setelah diterbitkan.</p></div>
        @else
            <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($listings as $listing)
                    <x-listing-card :listing="$listing" />
                @endforeach
            </div>
            {{ $listings->links() }}
        @endif
    </section>
@endsection
