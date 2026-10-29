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

<div class="mx-auto max-w-7xl space-y-6 p-4 sm:p-6">
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
    @php($prospects = Prospect::visibleTo(auth()->user())->with(['listing', 'owner'])->when($stageFilter !== '', fn ($query) => $query->where('stage', $stageFilter))->when($ownerFilter !== '' && auth()->user()->isAdmin(), fn ($query) => $query->where('owner_id', $ownerFilter))->when($search !== '', fn ($query) => $query->where(fn ($inner) => $inner->where('name', 'ILIKE', '%'.$search.'%')->orWhere('phone_e164', 'LIKE', '%'.$search.'%')))->latest()->latest('id')->paginate(15))
    @php($today = now('Asia/Jakarta')->startOfDay())
    <div class="rounded-xl border border-zinc-200 px-4 dark:border-zinc-700">
        <flux:table>
            <flux:table.columns>
                <flux:table.column>Prospek</flux:table.column>
                <flux:table.column>Properti</flux:table.column>
                @if (auth()->user()->isAdmin())<flux:table.column>Sales</flux:table.column>@endif
                <flux:table.column>Tahap</flux:table.column>
                <flux:table.column>Sumber</flux:table.column>
                <flux:table.column>Tindak lanjut</flux:table.column>
                <flux:table.column>Masuk</flux:table.column>
                <flux:table.column align="end"><span class="sr-only">Aksi</span></flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($prospects as $prospect)
                    @php($open = ! in_array($prospect->stage, ['berhasil', 'gagal'], true))
                    <flux:table.row :key="$prospect->id">
                        <flux:table.cell variant="strong">
                            <a class="hover:underline" href="{{ route('prospects.show', $prospect) }}" wire:navigate>{{ $prospect->name }}</a>
                            <span class="block text-xs font-normal tabular-nums text-zinc-500">{{ $prospect->phone_e164 }}</span>
                        </flux:table.cell>
                        <flux:table.cell>
                            <span class="block max-w-56 truncate text-zinc-700 dark:text-zinc-200">{{ $prospect->listing->title }}</span>
                            <span class="block text-xs">{{ $prospect->listing->code }}</span>
                        </flux:table.cell>
                        @if (auth()->user()->isAdmin())<flux:table.cell>{{ $prospect->owner->name }}</flux:table.cell>@endif
                        <flux:table.cell><flux:badge size="sm" :color="Prospect::STAGE_COLORS[$prospect->stage] ?? 'zinc'">{{ ucfirst($prospect->stage) }}</flux:badge></flux:table.cell>
                        <flux:table.cell>{{ $prospect->source }}</flux:table.cell>
                        <flux:table.cell>
                            @if (! $prospect->followup_on || ! $open)
                                <span class="text-zinc-400">–</span>
                            @elseif ($prospect->followup_on->lt($today))
                                <flux:badge size="sm" color="red">Terlambat · {{ $prospect->followup_on->format('d/m') }}</flux:badge>
                            @elseif ($prospect->followup_on->isSameDay($today))
                                <flux:badge size="sm" color="amber">Hari ini</flux:badge>
                            @else
                                <span class="tabular-nums">{{ $prospect->followup_on->format('d/m/Y') }}</span>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell class="tabular-nums">{{ $prospect->created_at->timezone('Asia/Jakarta')->format('d/m/Y') }}</flux:table.cell>
                        <flux:table.cell align="end">
                            <flux:button size="sm" variant="ghost" icon="arrow-right" :href="route('prospects.show', $prospect)" aria-label="Lihat prospek {{ $prospect->name }}" />
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row><flux:table.cell colspan="8">Belum ada prospek yang cocok.</flux:table.cell></flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>
    {{ $prospects->links() }}
</div>
