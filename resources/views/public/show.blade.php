@extends('layouts.public')

@php($photos = $listing->media->where('kind', 'photo')->values())
@php($floorplan = $listing->media->firstWhere('kind', 'floorplan'))
@php($specs = array_filter([
    'Luas tanah' => $listing->land_area_m2 ? number_format((float) $listing->land_area_m2, 0, ',', '.').' m²' : null,
    'Luas bangunan' => $listing->building_area_m2 ? number_format((float) $listing->building_area_m2, 0, ',', '.').' m²' : null,
    'Kamar' => $listing->rooms,
    'Kamar mandi' => $listing->bathrooms,
], fn ($value) => $value !== null))

@section('title', $listing->title)
@section('description', \Illuminate\Support\Str::limit(strip_tags($listing->description ?? $listing->title), 155))
@if ($photos->first())
    @section('image', $photos->first()->url)
@endif

@section('content')
    <div class="mx-auto max-w-7xl space-y-8 px-4 py-8 md:px-8 md:py-10">
        <nav aria-label="Breadcrumb" class="text-sm text-stone-500">
            <a href="{{ route('properties.index') }}" class="hover:text-stone-950">← Semua properti</a>
        </nav>

        <div class="flex flex-wrap items-end justify-between gap-6">
            <div class="space-y-3">
                <p class="text-xs font-medium uppercase tracking-[0.25em] text-stone-500">{{ ucfirst($listing->type) }} · {{ $listing->code }}</p>
                <h1 class="text-3xl font-medium tracking-tight md:text-5xl">{{ $listing->title }}</h1>
                <p class="text-stone-500">{{ $listing->district ? $listing->district.', ' : '' }}{{ $listing->city }}, {{ $listing->province }}</p>
            </div>
        </div>

        @if ($photos->isNotEmpty())
            <div class="grid grid-cols-2 gap-3 md:grid-cols-3">
                @foreach ($photos as $photo)
                    <img src="{{ $photo->url }}" alt="Foto {{ $loop->iteration }} {{ $listing->title }}" @class(['w-full rounded-2xl object-cover', 'col-span-2 aspect-[4/3] md:row-span-2 md:aspect-auto md:h-full' => $loop->first, 'aspect-[4/3]' => ! $loop->first]) loading="{{ $loop->first ? 'eager' : 'lazy' }}">
                @endforeach
            </div>
        @else
            {{-- aset: foto properti --}}
            <div class="aspect-[16/7] rounded-3xl bg-stone-200"><span class="sr-only">Foto belum tersedia</span></div>
        @endif

        <div class="grid gap-10 lg:grid-cols-[minmax(0,2fr)_minmax(20rem,1fr)]">
            <div class="divide-y divide-stone-200">
                @if ($specs)
                    <dl class="grid grid-cols-2 gap-3 pb-8 sm:grid-cols-4">
                        @foreach ($specs as $label => $value)
                            <div class="flex flex-col-reverse justify-end rounded-2xl bg-stone-50 p-4">
                                <dt class="mt-1 text-xs text-stone-500">{{ $label }}</dt>
                                <dd class="text-lg font-medium">{{ $value }}</dd>
                            </div>
                        @endforeach
                    </dl>
                @endif
                <section class="space-y-3 py-8">
                    <h2 class="text-xl font-medium">Tentang properti</h2>
                    <p class="whitespace-pre-line leading-relaxed text-stone-700">{{ $listing->description }}</p>
                </section>
                @if ($listing->facilities)
                    <section class="space-y-3 py-8">
                        <h2 class="text-xl font-medium">Fasilitas</h2>
                        <ul class="flex flex-wrap gap-2">
                            @foreach ($listing->facilities as $facility)
                                <li class="rounded-full border border-stone-200 px-3 py-1 text-sm">{{ $facility }}</li>
                            @endforeach
                        </ul>
                    </section>
                @endif
                @if ($floorplan)
                    <section class="space-y-3 py-8">
                        <h2 class="text-xl font-medium">Denah</h2>
                        @if ($floorplan->mime_type === 'application/pdf')
                            <a href="{{ $floorplan->url }}" target="_blank" rel="noopener" class="font-medium underline underline-offset-4">Buka denah PDF</a>
                        @else
                            <img src="{{ $floorplan->url }}" alt="Denah {{ $listing->title }}" class="max-w-full rounded-2xl border border-stone-200">
                        @endif
                    </section>
                @endif
                <section class="space-y-2 py-8">
                    <h2 class="text-xl font-medium">Lokasi</h2>
                    <p class="text-stone-700">{{ $listing->address ?: ($listing->district ? $listing->district.', ' : '').$listing->city.', '.$listing->province }}</p>
                    @if ($listing->latitude !== null && $listing->longitude !== null)
                        <a href="https://www.openstreetmap.org/?mlat={{ $listing->latitude }}&amp;mlon={{ $listing->longitude }}#map=15/{{ $listing->latitude }}/{{ $listing->longitude }}" target="_blank" rel="noopener" class="inline-block font-medium underline underline-offset-4">Buka peta</a>
                    @endif
                </section>
            </div>

            <aside class="h-fit space-y-6 rounded-3xl border border-stone-200 p-6 lg:sticky lg:top-24">
                <div class="space-y-3">
                    <span class="inline-block rounded-full bg-stone-100 px-3 py-1 text-xs font-medium">Status: {{ ['available' => 'Tersedia', 'reserved' => 'Dipesan', 'sold' => 'Terjual'][$listing->availability_status] }}</span>
                    <div><p class="text-sm text-stone-500">Harga jual</p><p class="mt-1 whitespace-nowrap text-3xl font-medium tracking-tight">Rp {{ number_format($listing->price_idr, 0, ',', '.') }}</p></div>
                </div>
                <p class="text-xs leading-relaxed text-stone-500">Informasi dan ketersediaan dapat berubah.</p>
                <section class="space-y-3 border-t border-stone-200 pt-6">
                    <h2 class="font-medium">Hubungi sales</h2>
                    @forelse ($salesContacts as $sales)
                        <x-sales-contact :sales="$sales" :listing="$listing" />
                    @empty
                        <p class="text-sm text-stone-600">Belum ada sales yang dapat dihubungi.</p>
                    @endforelse
                </section>
            </aside>
        </div>
        <x-mortgage-calculator :listing="$listing" />
    </div>
@endsection
