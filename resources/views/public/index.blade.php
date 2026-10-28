@extends('layouts.public')

@section('title', 'Semua properti tersedia')
@section('description', 'Katalog rumah, tanah, dan bangunan kos yang diterbitkan dan tersedia.')

@section('content')
    <section class="mx-auto max-w-7xl space-y-7 px-5 py-12 md:px-8">
        <div class="space-y-2"><p class="text-sm font-semibold uppercase tracking-wider text-emerald-700">Katalog</p><h1 class="text-4xl font-semibold">Properti tersedia</h1><p class="text-stone-600">Pilih properti untuk melihat foto dan informasi lengkap.</p></div>
        <form id="property-filters" method="GET" action="{{ route('properties.index') }}" class="space-y-4 rounded-2xl border border-stone-200 bg-white p-5">
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <label class="space-y-1 text-sm font-medium">Kata kunci<input name="q" value="{{ $filters['q'] ?? '' }}" maxlength="100" placeholder="Judul, fasilitas, lokasi" class="w-full rounded-lg border border-stone-300 px-3 py-2"></label>
                <label class="space-y-1 text-sm font-medium">Kabupaten/kota<select id="filter-city" name="city" class="w-full rounded-lg border border-stone-300 px-3 py-2"><option value="">Semua lokasi</option>@foreach ($cities as $city)<option value="{{ $city }}" @selected(($filters['city'] ?? '') === $city)>{{ $city }}</option>@endforeach</select></label>
                <label class="space-y-1 text-sm font-medium">Kecamatan<select id="filter-district" name="district" class="w-full rounded-lg border border-stone-300 px-3 py-2"><option value="">Semua kecamatan</option>@foreach ($locations as $location)<option value="{{ $location->district }}" data-city="{{ $location->city }}" @selected(($filters['district'] ?? '') === $location->district && ($filters['city'] ?? '') === $location->city)>{{ $location->district }}</option>@endforeach</select></label>
                <label class="space-y-1 text-sm font-medium">Tipe<select name="type" class="w-full rounded-lg border border-stone-300 px-3 py-2"><option value="">Semua tipe</option>@foreach (['rumah' => 'Rumah', 'tanah' => 'Tanah', 'kos' => 'Bangunan kos'] as $value => $label)<option value="{{ $value }}" @selected(($filters['type'] ?? '') === $value)>{{ $label }}</option>@endforeach</select></label>
                @foreach (['price_min' => 'Harga min (Rp)', 'price_max' => 'Harga maks (Rp)', 'land_min' => 'Luas tanah min (m²)', 'land_max' => 'Luas tanah maks (m²)', 'building_min' => 'Luas bangunan min (m²)', 'building_max' => 'Luas bangunan maks (m²)', 'rooms_min' => 'Kamar minimum'] as $field => $label)
                    <label class="space-y-1 text-sm font-medium">{{ $label }}<input name="{{ $field }}" type="number" min="0" @if ($field === 'rooms_min' || str_starts_with($field, 'price_')) step="1" @else step="0.01" @endif value="{{ $filters[$field] ?? '' }}" class="w-full rounded-lg border border-stone-300 px-3 py-2"></label>
                @endforeach
            </div>
            @if ($filterErrors->isNotEmpty())
                <div role="alert" class="rounded-lg bg-red-50 p-3 text-sm text-red-800"><strong>Perbaiki filter:</strong><ul class="ml-5 list-disc">@foreach ($filterErrors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
            @endif
            <div class="flex flex-wrap gap-3"><button type="submit" class="rounded-lg bg-emerald-700 px-5 py-2 font-semibold text-white hover:bg-emerald-800">Cari</button><a href="{{ route('properties.index') }}" class="rounded-lg border border-stone-300 px-5 py-2 font-semibold hover:bg-stone-100">Reset</a></div>
        </form>
        @if ($listings->isEmpty())
            <div class="rounded-xl border border-stone-200 bg-white p-8"><h2 class="text-xl font-semibold">{{ $filterErrors->isNotEmpty() ? 'Perbaiki filter untuk melihat hasil' : ($hasFilters ? 'Tidak ada properti yang cocok' : 'Belum ada properti tersedia') }}</h2><p class="mt-2 text-stone-600">{{ $hasFilters ? 'Coba ubah kriteria atau gunakan Reset.' : 'Listing baru akan muncul di sini setelah diterbitkan.' }}</p></div>
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
