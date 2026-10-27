@props(['listing'])

@php($photo = $listing->media->firstWhere('kind', 'photo'))
<article class="overflow-hidden rounded-2xl border border-stone-200 bg-white shadow-sm transition hover:shadow-md">
    <a href="{{ route('properties.show', $listing) }}" class="block focus:outline-2 focus:outline-offset-2 focus:outline-emerald-700">
        @if ($photo)
            <img src="{{ $photo->url }}" alt="Foto utama {{ $listing->title }}" class="aspect-[4/3] w-full object-cover" loading="lazy">
        @else
            <div class="flex aspect-[4/3] items-center justify-center bg-stone-100 text-stone-500">Foto belum tersedia</div>
        @endif
        <div class="space-y-2 p-5">
            <p class="text-xs font-semibold uppercase tracking-wider text-emerald-700">{{ ucfirst($listing->type) }} · {{ $listing->code }}</p>
            <h2 class="text-xl font-semibold leading-tight">{{ $listing->title }}</h2>
            <p class="font-semibold">Rp {{ number_format($listing->price_idr, 0, ',', '.') }}</p>
            <p class="text-sm text-stone-600">{{ $listing->district ? $listing->district.', ' : '' }}{{ $listing->city }}, {{ $listing->province }}</p>
            <p class="text-sm text-stone-600">
                @if ($listing->land_area_m2)
                    {{ number_format((float) $listing->land_area_m2, 0, ',', '.') }} m² tanah
                @endif
                @if ($listing->building_area_m2)
                    · {{ number_format((float) $listing->building_area_m2, 0, ',', '.') }} m² bangunan
                @endif
                @if ($listing->rooms !== null)
                    · {{ $listing->rooms }} kamar
                @endif
            </p>
        </div>
    </a>
</article>
