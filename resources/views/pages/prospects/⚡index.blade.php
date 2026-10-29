<?php

use App\Models\Prospect;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Prospek')] class extends Component {
    use WithPagination;

    public string $search = '';
    public string $stageFilter = '';
    public string $ownerFilter = '';

    public function mount(): void
    {
        Gate::authorize('viewAny', Prospect::class);
    }

    public function updated(): void
    {
        $this->resetPage();
    }
}; ?>

<div class="mx-auto max-w-6xl space-y-6 p-6">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div><flux:heading size="xl">Prospek</flux:heading><flux:text>{{ auth()->user()->isAdmin() ? 'Seluruh prospek tim.' : 'Prospek yang Anda tangani.' }}</flux:text></div>
        <flux:button variant="primary" :href="route('prospects.create')">Tambah prospek</flux:button>
    </div>
    <div class="grid gap-3 rounded-xl border border-zinc-200 p-4 dark:border-zinc-700 md:grid-cols-3">
        <flux:input wire:model.live.debounce.300ms="search" label="Cari nama atau nomor" />
        <flux:select wire:model.live="stageFilter" label="Tahap">
            <flux:select.option value="">Semua tahap</flux:select.option>
            @foreach (Prospect::STAGES as $stage)<flux:select.option :value="$stage">{{ ucfirst($stage) }}</flux:select.option>@endforeach
        </flux:select>
        @if (auth()->user()->isAdmin())
            <flux:select wire:model.live="ownerFilter" label="Sales">
                <flux:select.option value="">Semua sales</flux:select.option>
                @foreach (User::where('role', 'sales')->orderBy('name')->get() as $sales)<flux:select.option :value="(string) $sales->id">{{ $sales->name }}</flux:select.option>@endforeach
            </flux:select>
        @endif
    </div>
    @php($prospects = Prospect::visibleTo(auth()->user())->with(['listing', 'owner'])->when($stageFilter !== '', fn ($query) => $query->where('stage', $stageFilter))->when($ownerFilter !== '' && auth()->user()->isAdmin(), fn ($query) => $query->where('owner_id', $ownerFilter))->when($search !== '', fn ($query) => $query->where(fn ($inner) => $inner->where('name', 'ILIKE', '%'.$search.'%')->orWhere('phone_e164', 'LIKE', '%'.$search.'%')))->latest()->paginate(12))
    <div class="grid gap-4 md:grid-cols-2">
        @forelse ($prospects as $prospect)
            <article class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
                <div class="text-sm text-zinc-500">{{ $prospect->listing->code }} · {{ ucfirst($prospect->stage) }} · {{ $prospect->owner->name }}</div>
                <h2 class="mt-2 text-lg font-semibold">{{ $prospect->name }}</h2>
                <p class="text-sm">{{ $prospect->listing->title }} · {{ $prospect->phone_e164 }}</p>
                <p class="mt-1 text-sm text-zinc-500">Tindak lanjut: {{ $prospect->followup_on?->format('d/m/Y') ?? 'Belum dijadwalkan' }}</p>
                <flux:button class="mt-4" :href="route('prospects.show', $prospect)">Lihat prospek</flux:button>
            </article>
        @empty
            <p>Belum ada prospek yang cocok.</p>
        @endforelse
    </div>
    {{ $prospects->links() }}
</div>
