@extends('layouts.public')

@section('title', 'Semua properti tersedia')
@section('description', 'Katalog rumah, tanah, dan bangunan kos yang diterbitkan dan tersedia.')

@php($inputClass = 'mt-1.5 block w-full rounded-xl border border-stone-200 bg-white px-3 py-2.5 text-sm text-stone-950 focus:border-stone-950 focus:outline-none focus:ring-1 focus:ring-stone-950 disabled:bg-stone-50 disabled:text-stone-400')
@php($advancedFields = ['price_min' => 'Harga min (Rp)', 'price_max' => 'Harga maks (Rp)', 'land_min' => 'Luas tanah min (m²)', 'land_max' => 'Luas tanah maks (m²)', 'building_min' => 'Luas bangunan min (m²)', 'building_max' => 'Luas bangunan maks (m²)', 'rooms_min' => 'Kamar minimum'])

@section('content')
    <section class="mx-auto max-w-7xl space-y-10 px-4 py-12 md:px-8 md:py-16">
        <div class="grid gap-4 md:grid-cols-2 md:items-end">
            <div>
                <p class="text-xs font-medium uppercase tracking-[0.25em] text-stone-500">Katalog</p>
                <h1 class="mt-3 text-4xl font-medium tracking-tight md:text-5xl">Properti tersedia</h1>
            </div>
            <p class="text-sm text-stone-500 md:justify-self-end md:text-right">Pilih properti untuk melihat foto dan informasi lengkap.</p>
        </div>

        <form id="property-filters" method="GET" action="{{ route('properties.index') }}" class="rounded-2xl border border-stone-200 p-4 md:p-5">
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <label class="text-xs text-stone-500">Kata kunci<input name="q" value="{{ $filters['q'] ?? '' }}" maxlength="100" placeholder="Judul, fasilitas, lokasi" class="{{ $inputClass }}"></label>
                <label class="text-xs text-stone-500">Kabupaten/kota<select id="filter-city" name="city" class="{{ $inputClass }}"><option value="">Semua lokasi</option>@foreach ($cities as $city)<option value="{{ $city }}" @selected(($filters['city'] ?? '') === $city)>{{ $city }}</option>@endforeach</select></label>
                <label class="text-xs text-stone-500">Kecamatan<select id="filter-district" name="district" class="{{ $inputClass }}"><option value="">Semua kecamatan</option>@foreach ($locations as $location)<option value="{{ $location->district }}" data-city="{{ $location->city }}" @selected(($filters['district'] ?? '') === $location->district && ($filters['city'] ?? '') === $location->city)>{{ $location->district }}</option>@endforeach</select></label>
                <label class="text-xs text-stone-500">Tipe<select name="type" class="{{ $inputClass }}"><option value="">Semua tipe</option>@foreach (['rumah' => 'Rumah', 'tanah' => 'Tanah', 'kos' => 'Bangunan kos'] as $value => $label)<option value="{{ $value }}" @selected(($filters['type'] ?? '') === $value)>{{ $label }}</option>@endforeach</select></label>
            </div>
            <details class="group mt-4 border-t border-stone-200 pt-4" @if ($filterErrors->isNotEmpty() || collect($advancedFields)->keys()->contains(fn ($field) => ($filters[$field] ?? '') !== '')) open @endif>
                <summary class="flex cursor-pointer list-none items-center gap-2 text-sm font-medium">Filter lanjutan <span aria-hidden="true" class="text-stone-400 transition group-open:rotate-45">+</span></summary>
                <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach ($advancedFields as $field => $label)
                        <label class="text-xs text-stone-500">{{ $label }}<input name="{{ $field }}" type="number" min="0" @if ($field === 'rooms_min' || str_starts_with($field, 'price_')) step="1" @else step="0.01" @endif value="{{ $filters[$field] ?? '' }}" class="{{ $inputClass }}"></label>
                    @endforeach
                </div>
            </details>
            @if ($filterErrors->isNotEmpty())
                <div role="alert" class="mt-4 rounded-xl bg-red-50 p-3 text-sm text-red-800"><strong>Perbaiki filter:</strong><ul class="ml-5 list-disc">@foreach ($filterErrors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
            @endif
            <div class="mt-4 flex flex-wrap gap-3 border-t border-stone-200 pt-4">
                <button type="submit" class="rounded-full bg-stone-950 px-6 py-2.5 text-sm font-medium text-white hover:bg-stone-800">Cari</button>
                <a href="{{ route('properties.index') }}" class="rounded-full border border-stone-300 px-6 py-2.5 text-sm font-medium hover:border-stone-950">Reset</a>
            </div>
        </form>

        @if ($listings->isEmpty())
            <div class="rounded-2xl border border-stone-200 p-8"><h2 class="text-xl font-medium">{{ $filterErrors->isNotEmpty() ? 'Perbaiki filter untuk melihat hasil' : ($hasFilters ? 'Tidak ada properti yang cocok' : 'Belum ada properti tersedia') }}</h2><p class="mt-2 text-stone-600">{{ $hasFilters ? 'Coba ubah kriteria atau gunakan Reset.' : 'Listing baru akan muncul di sini setelah diterbitkan.' }}</p></div>
        @else
            <p class="text-sm text-stone-500">{{ $listings->total() }} properti ditemukan</p>
            <div class="grid gap-x-6 gap-y-10 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($listings as $listing)
                    <x-listing-card :listing="$listing" />
                @endforeach
            </div>
            {{ $listings->links() }}
        @endif
    </section>
@endsection
