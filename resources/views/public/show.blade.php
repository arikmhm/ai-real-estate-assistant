@extends('layouts.public')

@php($photos = $listing->media->where('kind', 'photo'))
@php($floorplan = $listing->media->firstWhere('kind', 'floorplan'))

@section('title', $listing->title)
@section('description', \Illuminate\Support\Str::limit(strip_tags($listing->description ?? $listing->title), 155))
@if ($photos->first())
    @section('image', $photos->first()->url)
@endif

@section('content')
    <div class="mx-auto max-w-7xl space-y-8 px-5 py-8 md:px-8">
        <a href="{{ route('properties.index') }}" class="text-sm font-medium text-emerald-700 hover:underline">← Semua properti</a>
        <div class="space-y-2">
            <p class="text-sm font-semibold uppercase tracking-wider text-emerald-700">{{ ucfirst($listing->type) }} · {{ $listing->code }}</p>
            <h1 class="text-4xl font-semibold">{{ $listing->title }}</h1>
            <p class="text-stone-600">{{ $listing->district ? $listing->district.', ' : '' }}{{ $listing->city }}, {{ $listing->province }}</p>
        </div>
        <div class="grid gap-8 lg:grid-cols-[minmax(0,2fr)_minmax(18rem,1fr)]">
            <div class="space-y-8">
                @if ($photos->isNotEmpty())
                    <div class="grid gap-3 sm:grid-cols-2">
                        @foreach ($photos as $photo)
                            <img src="{{ $photo->url }}" alt="Foto {{ $loop->iteration }} {{ $listing->title }}" class="aspect-[4/3] w-full rounded-xl object-cover" loading="lazy">
                        @endforeach
                    </div>
                @endif
                <section class="space-y-4">
                    <h2 class="text-2xl font-semibold">Tentang properti</h2>
                    <p class="whitespace-pre-line leading-relaxed text-stone-700">{{ $listing->description }}</p>
                </section>
                @if ($listing->facilities)
                    <section>
                        <h2 class="mb-3 text-2xl font-semibold">Fasilitas</h2>
                        <ul class="flex flex-wrap gap-2">
                            @foreach ($listing->facilities as $facility)
                                <li class="rounded-full bg-stone-100 px-3 py-1 text-sm">{{ $facility }}</li>
                            @endforeach
                        </ul>
                    </section>
                @endif
                @if ($floorplan)
                    <section class="space-y-3">
                        <h2 class="text-2xl font-semibold">Denah</h2>
                        @if ($floorplan->mime_type === 'application/pdf')
                            <a href="{{ $floorplan->url }}" target="_blank" rel="noopener" class="font-medium text-emerald-700 underline">Buka denah PDF</a>
                        @else
                            <img src="{{ $floorplan->url }}" alt="Denah {{ $listing->title }}" class="max-w-full rounded-xl">
                        @endif
                    </section>
                @endif
                <section class="space-y-2">
                    <h2 class="text-2xl font-semibold">Lokasi</h2>
                    <p>{{ $listing->address ?: ($listing->district ? $listing->district.', ' : '').$listing->city.', '.$listing->province }}</p>
                    @if ($listing->latitude !== null && $listing->longitude !== null)
                        <a href="https://www.openstreetmap.org/?mlat={{ $listing->latitude }}&amp;mlon={{ $listing->longitude }}#map=15/{{ $listing->latitude }}/{{ $listing->longitude }}" target="_blank" rel="noopener" class="font-medium text-emerald-700 underline">Buka peta</a>
                    @endif
                </section>
            </div>
            <aside class="h-fit space-y-5 rounded-2xl border border-stone-200 bg-white p-6 lg:sticky lg:top-6">
                <div><p class="text-sm text-stone-500">Harga jual</p><p class="text-3xl font-semibold">Rp {{ number_format($listing->price_idr, 0, ',', '.') }}</p></div>
                <div class="rounded-lg bg-stone-100 px-3 py-2 text-sm font-medium">Status: {{ ['available' => 'Tersedia', 'reserved' => 'Dipesan', 'sold' => 'Terjual'][$listing->availability_status] }}</div>
                <dl class="space-y-3 text-sm">
                    @if ($listing->land_area_m2)
                        <div class="flex justify-between"><dt>Luas tanah</dt><dd>{{ number_format((float) $listing->land_area_m2, 0, ',', '.') }} m²</dd></div>
                    @endif
                    @if ($listing->building_area_m2)
                        <div class="flex justify-between"><dt>Luas bangunan</dt><dd>{{ number_format((float) $listing->building_area_m2, 0, ',', '.') }} m²</dd></div>
                    @endif
                    @if ($listing->rooms !== null)
                        <div class="flex justify-between"><dt>Kamar</dt><dd>{{ $listing->rooms }}</dd></div>
                    @endif
                    @if ($listing->bathrooms !== null)
                        <div class="flex justify-between"><dt>Kamar mandi</dt><dd>{{ $listing->bathrooms }}</dd></div>
                    @endif
                </dl>
                <p class="text-xs leading-relaxed text-stone-500">Informasi dan ketersediaan dapat berubah.</p>
            </aside>
        </div>
    </div>
@endsection
