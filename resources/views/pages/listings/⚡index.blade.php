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

<div class="mx-auto max-w-6xl space-y-6 p-6">
    <div class="flex items-center justify-between gap-4">
        <div>
            <flux:heading size="xl">Inventaris properti</flux:heading>
            <flux:text>Semua listing, termasuk draf dan arsip.</flux:text>
        </div>
        @can('create', Listing::class)
            <flux:button variant="primary" :href="route('listings.create')">Tambah properti</flux:button>
        @endcan
    </div>
    @php($listings = Listing::with('media')->latest()->paginate(12))
    <div class="grid gap-4 md:grid-cols-2">
        @forelse ($listings as $listing)
            <article class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
                <div class="text-sm text-zinc-500">{{ $listing->code }} · {{ ucfirst($listing->publication_status) }} · {{ $listing->availability_label }}</div>
                <h2 class="mt-2 text-lg font-semibold">{{ $listing->title }}</h2>
                <p class="text-sm text-zinc-600 dark:text-zinc-300">{{ ucfirst($listing->type) }} · {{ $listing->city }}, {{ $listing->province }}</p>
                <div class="mt-4 flex gap-3">
                    <flux:button :href="route('listings.show', $listing)">Lihat</flux:button>
                    @can('update', $listing)
                        <flux:button :href="route('listings.edit', $listing)">Ubah</flux:button>
                    @endcan
                </div>
            </article>
        @empty
            <p>Belum ada properti.</p>
        @endforelse
    </div>
    {{ $listings->links() }}
</div>
