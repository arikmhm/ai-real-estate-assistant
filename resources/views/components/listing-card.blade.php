@props(['listing'])

@php($photo = $listing->media->firstWhere('kind', 'photo'))
<article class="group bg-white ring-1 ring-stone-200/70 transition hover:shadow-lg hover:shadow-stone-900/5">
    <a href="{{ route('properties.show', $listing) }}" class="block focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-stone-950">
        @if ($photo)
            <img src="{{ $photo->url }}" alt="Foto utama {{ $listing->title }}" class="aspect-[11/10] w-full object-cover" loading="lazy">
        @else
            {{-- aset: foto properti --}}
            <div class="aspect-[11/10] w-full bg-stone-200"><span class="sr-only">Foto belum tersedia</span></div>
        @endif
        <div class="space-y-1 p-5">
            <div class="flex items-start justify-between gap-3">
                <h2 class="text-base leading-snug group-hover:underline group-hover:underline-offset-4">{{ $listing->title }}</h2>
                <p class="shrink-0 text-base">Rp {{ number_format($listing->price_idr, 0, ',', '.') }}</p>
            </div>
            <p class="text-xs text-stone-500">{{ $listing->province }}/{{ $listing->city }}{{ $listing->district ? '/'.$listing->district : '' }}</p>
            <p class="flex flex-wrap gap-x-4 gap-y-1 pt-3 text-xs text-stone-600">
                @if ($listing->land_area_m2)
                    <span class="inline-flex items-center gap-1.5"><flux:icon.arrows-pointing-out variant="micro" class="size-3.5 text-stone-400" />{{ number_format((float) $listing->land_area_m2, 0, ',', '.') }} m² tanah</span>
                @endif
                @if ($listing->building_area_m2)
                    <span class="inline-flex items-center gap-1.5"><flux:icon.home-modern variant="micro" class="size-3.5 text-stone-400" />{{ number_format((float) $listing->building_area_m2, 0, ',', '.') }} m² bangunan</span>
                @endif
                @if ($listing->rooms !== null)
                    <span class="inline-flex items-center gap-1.5"><flux:icon.key variant="micro" class="size-3.5 text-stone-400" />{{ $listing->rooms }} kamar</span>
                @endif
            </p>
        </div>
    </a>
</article>
