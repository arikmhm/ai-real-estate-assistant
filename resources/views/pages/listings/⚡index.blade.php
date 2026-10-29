<?php

use App\Models\Listing;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Inventaris properti')] class extends Component {
    use WithPagination;

    public function mount(): void
    {
        Gate::authorize('viewAny', Listing::class);
    }
}; ?>

<div class="mx-auto max-w-7xl space-y-6 p-4 sm:p-6">
    <div class="flex items-center justify-between gap-4">
        <div>
            <flux:heading size="xl">Inventaris properti</flux:heading>
            <flux:text>Semua listing, termasuk draf dan arsip.</flux:text>
        </div>
        @can('create', Listing::class)
            <flux:button variant="primary" :href="route('listings.create')">Tambah properti</flux:button>
        @endcan
    </div>
    @php($listings = Listing::with(['media' => fn ($query) => $query->where('kind', 'photo')])->latest()->latest('id')->paginate(12))
    <div class="rounded-xl border border-zinc-200 px-4 dark:border-zinc-700">
        <flux:table>
            <flux:table.columns>
                <flux:table.column>Properti</flux:table.column>
                <flux:table.column>Kecamatan</flux:table.column>
                <flux:table.column align="end">Harga</flux:table.column>
                <flux:table.column align="end">LT / LB</flux:table.column>
                <flux:table.column align="end">KT / KM</flux:table.column>
                <flux:table.column>Publikasi</flux:table.column>
                <flux:table.column>Ketersediaan</flux:table.column>
                <flux:table.column align="end"><span class="sr-only">Aksi</span></flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($listings as $listing)
                    @php($cover = $listing->media->first())
                    <flux:table.row :key="$listing->id">
                        <flux:table.cell variant="strong">
                            <a href="{{ route('listings.show', $listing) }}" wire:navigate class="flex items-center gap-3">
                                @if ($cover)
                                    <img src="{{ $cover->url }}" alt="" loading="lazy" class="h-14 w-20 shrink-0 rounded-lg object-cover">
                                @else
                                    <span class="flex h-14 w-20 shrink-0 items-center justify-center rounded-lg bg-zinc-100 text-zinc-400 dark:bg-zinc-800"><flux:icon.photo variant="mini" /></span>
                                @endif
                                <span class="min-w-0">
                                    <span class="block max-w-56 truncate hover:underline">{{ $listing->title }}</span>
                                    <span class="block text-xs font-normal text-zinc-500">{{ $listing->code }} · {{ ucfirst($listing->type) }}</span>
                                </span>
                            </a>
                        </flux:table.cell>
                        <flux:table.cell>{{ $listing->district ?? $listing->city }}</flux:table.cell>
                        <flux:table.cell align="end" variant="strong" class="tabular-nums">Rp{{ number_format($listing->price_idr, 0, ',', '.') }}</flux:table.cell>
                        <flux:table.cell align="end" class="tabular-nums">{{ $listing->land_area_m2 ? (float) $listing->land_area_m2 : '–' }} / {{ $listing->building_area_m2 ? (float) $listing->building_area_m2 : '–' }} m²</flux:table.cell>
                        <flux:table.cell align="end" class="tabular-nums">{{ $listing->rooms ?? '–' }} / {{ $listing->bathrooms ?? '–' }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="['draft' => 'zinc', 'published' => 'green', 'archived' => 'amber'][$listing->publication_status] ?? 'zinc'">{{ $listing->publication_label }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="['available' => 'sky', 'reserved' => 'orange', 'sold' => 'emerald'][$listing->availability_status] ?? 'zinc'">{{ $listing->availability_label }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell align="end">
                            <div class="flex justify-end gap-1">
                                <flux:button size="sm" variant="ghost" icon="eye" :href="route('listings.show', $listing)" aria-label="Lihat {{ $listing->title }}" />
                                @can('update', $listing)
                                    <flux:button size="sm" variant="ghost" icon="pencil-square" :href="route('listings.edit', $listing)" aria-label="Ubah {{ $listing->title }}" />
                                @endcan
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row><flux:table.cell colspan="8">Belum ada properti.</flux:table.cell></flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>
    {{ $listings->links() }}
</div>
