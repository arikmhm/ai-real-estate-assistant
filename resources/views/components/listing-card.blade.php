@props(['listing'])

@php($photo = $listing->media->firstWhere('kind', 'photo'))
<article class="group">
    <a href="{{ route('properties.show', $listing) }}" class="block rounded-2xl focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-stone-950">
        <div class="relative overflow-hidden rounded-2xl bg-stone-200">
            @if ($photo)
                <img src="{{ $photo->url }}" alt="Foto utama {{ $listing->title }}" class="aspect-[4/3] w-full object-cover transition duration-300 group-hover:scale-[1.03]" loading="lazy">
            @else
                {{-- aset: foto properti --}}
                <div class="aspect-[4/3] w-full"><span class="sr-only">Foto belum tersedia</span></div>
            @endif
            <span class="absolute left-3 top-3 rounded-full bg-white/90 px-2.5 py-1 text-xs font-medium backdrop-blur">{{ ucfirst($listing->type) }} · {{ $listing->code }}</span>
        </div>
        <div class="space-y-1 pt-4">
            <div class="flex items-start justify-between gap-3">
                <h2 class="font-medium leading-snug group-hover:underline group-hover:underline-offset-4">{{ $listing->title }}</h2>
                <p class="shrink-0 font-semibold">Rp {{ number_format($listing->price_idr, 0, ',', '.') }}</p>
            </div>
            <p class="text-sm text-stone-500">{{ $listing->district ? $listing->district.', ' : '' }}{{ $listing->city }}, {{ $listing->province }}</p>
            <p class="flex flex-wrap gap-x-3 pt-1 text-xs text-stone-600">
                @if ($listing->land_area_m2)
                    <span>{{ number_format((float) $listing->land_area_m2, 0, ',', '.') }} m² tanah</span>
                @endif
                @if ($listing->building_area_m2)
                    <span>{{ number_format((float) $listing->building_area_m2, 0, ',', '.') }} m² bangunan</span>
                @endif
                @if ($listing->rooms !== null)
                    <span>{{ $listing->rooms }} kamar</span>
                @endif
            </p>
        </div>
    </a>
</article>
