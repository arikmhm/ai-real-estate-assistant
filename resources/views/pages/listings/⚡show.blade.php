<?php

use App\Models\Listing;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Detail inventaris')] class extends Component {
    public Listing $listing;

    public function mount(Listing $listing): void
    {
        Gate::authorize('view', $listing);
        $this->listing = $listing->load('media');
    }
}; ?>

<div class="mx-auto max-w-4xl space-y-5 p-6">
    <flux:button :href="route('listings.index')" variant="ghost">← Inventaris</flux:button>
    <flux:heading size="xl">{{ $listing->title }}</flux:heading>
    <flux:text>{{ $listing->code }} · {{ ucfirst($listing->publication_status) }} · {{ ucfirst($listing->availability_status) }}</flux:text>
    <p>Rp {{ number_format($listing->price_idr, 0, ',', '.') }} · {{ ucfirst($listing->type) }} · {{ $listing->city }}, {{ $listing->province }}</p>
    @if ($listing->description)<p class="whitespace-pre-line">{{ $listing->description }}</p>@endif
    @if ($listing->land_area_m2)<p>Luas tanah: {{ $listing->land_area_m2 }} m²</p>@endif
    @if ($listing->building_area_m2)<p>Luas bangunan: {{ $listing->building_area_m2 }} m²</p>@endif
    @if ($listing->rooms !== null)<p>Kamar: {{ $listing->rooms }}</p>@endif
    @foreach ($listing->media as $media)
        @if ($media->kind === 'photo')<img src="{{ $media->url }}" alt="Foto {{ $listing->title }}" class="max-h-80 rounded-lg object-cover">@endif
        @if ($media->kind === 'floorplan')<a href="{{ $media->url }}" target="_blank" rel="noopener">Lihat denah</a>@endif
    @endforeach
    @can('update', $listing)<flux:button :href="route('listings.edit', $listing)">Ubah properti</flux:button>@endcan
</div>
