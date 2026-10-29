<?php

use App\Models\Prospect;
use App\Models\User;
use App\Models\Visit;
use App\Services\VisitService;
use Flux\Flux;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Kunjungan dan tindak lanjut')] class extends Component {
    use WithPagination;

    public string $ownerFilter = '';

    public function mount(): void
    {
        Gate::authorize('viewAny', Prospect::class);
    }

    public function updatedOwnerFilter(): void
    {
        $this->resetPage('historyPage');
        $this->resetPage('followupsPage');
    }

    /** Quick outcome from the agenda; time, location and notes stay as scheduled. */
    public function markVisit(int $visitId, string $status, VisitService $service): void
    {
        abort_unless(in_array($status, ['selesai', 'dibatalkan'], true), 422);
        $visit = Visit::with('prospect')->findOrFail($visitId);
        $service->update(auth()->user(), $visit, [
            'scheduled_at' => $visit->scheduled_at->timezone('Asia/Jakarta')->format('Y-m-d\TH:i'),
            'status' => $status, 'location' => $visit->location, 'notes' => $visit->notes,
        ]);
        Flux::toast(variant: 'success', text: $status === 'selesai' ? 'Kunjungan ditandai selesai.' : 'Kunjungan dibatalkan.');
    }
}; ?>

@php
    $user = auth()->user();
    $tz = 'Asia/Jakarta';
    $now = now($tz);
    $todayStart = $now->startOfDay();
    $visits = fn () => Visit::query()
        ->whereHas('prospect', fn ($query) => $query->visibleTo($user)
            ->when($user->isAdmin() && $ownerFilter !== '', fn ($inner) => $inner->where('owner_id', $ownerFilter)));
    $relations = ['prospect.owner', 'listing.media' => fn ($query) => $query->where('kind', 'photo')];

    $todayCount = $visits()->where('status', 'dijadwalkan')->whereBetween('scheduled_at', [$todayStart->utc(), $now->endOfDay()->utc()])->count();
    $weekCount = $visits()->where('status', 'dijadwalkan')->whereBetween('scheduled_at', [now(), now()->addDays(7)])->count();
    $recentOutcomes = $visits()->whereIn('status', ['selesai', 'dibatalkan'])->where('scheduled_at', '>=', now()->subDays(30))
        ->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');
    $doneCount = (int) ($recentOutcomes['selesai'] ?? 0);
    $cancelRate = $recentOutcomes->sum() > 0 ? round(($recentOutcomes['dibatalkan'] ?? 0) / $recentOutcomes->sum() * 100) : 0;

    $overdue = $visits()->with($relations)->where('status', 'dijadwalkan')->where('scheduled_at', '<', $todayStart->utc())->orderBy('scheduled_at')->get();
    $agenda = $visits()->with($relations)->where('status', 'dijadwalkan')->where('scheduled_at', '>=', $todayStart->utc())
        ->orderBy('scheduled_at')->orderBy('id')->limit(40)->get()
        ->groupBy(fn ($visit) => $visit->scheduled_at->timezone($tz)->toDateString());
    $history = $visits()->with($relations)->whereIn('status', ['selesai', 'dibatalkan'])
        ->orderByDesc('scheduled_at')->orderByDesc('id')->paginate(10, ['*'], 'historyPage');
    $followups = Prospect::visibleTo($user)->with(['listing', 'owner'])
        ->when($user->isAdmin() && $ownerFilter !== '', fn ($query) => $query->where('owner_id', $ownerFilter))
        ->whereNotNull('followup_on')->whereNotIn('stage', ['berhasil', 'gagal'])
        ->orderBy('followup_on')->orderBy('id')->paginate(8, ['*'], 'followupsPage');
    $dayLabel = fn (string $date) => match (true) {
        $date === $now->toDateString() => 'Hari ini',
        $date === $now->addDay()->toDateString() => 'Besok',
        default => Illuminate\Support\Carbon::parse($date, $tz)->translatedFormat('l, j F'),
    };
@endphp

<div class="mx-auto max-w-7xl space-y-6 p-4 sm:p-6">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <flux:heading size="xl">Kunjungan dan tindak lanjut</flux:heading>
            <flux:text>{{ $user->isAdmin() ? 'Agenda survei lokasi seluruh tim.' : 'Agenda survei lokasi prospek yang Anda tangani.' }}</flux:text>
        </div>
        @if ($user->isAdmin())
            <flux:select wire:model.live="ownerFilter" class="max-w-56" aria-label="Filter sales">
                <flux:select.option value="">Semua sales</flux:select.option>
                @foreach (User::where('role', 'sales')->orderBy('name')->get() as $sales)<flux:select.option :value="(string) $sales->id">{{ $sales->name }}</flux:select.option>@endforeach
            </flux:select>
        @endif
    </div>

    <section aria-label="Ringkasan kunjungan" class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ([
            ['Hari ini', $todayCount, 'kunjungan terjadwal', 'calendar'],
            ['7 hari ke depan', $weekCount, 'kunjungan terjadwal', 'calendar-days'],
            ['Selesai 30 hari', $doneCount, 'survei terlaksana', 'check-circle'],
            ['Rasio batal 30 hari', $cancelRate.'%', 'dari kunjungan yang lewat', 'x-circle'],
        ] as [$label, $value, $note, $icon])
            <div class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
                <div class="flex items-center justify-between gap-3">
                    <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ $label }}</p>
                    <span class="rounded-lg bg-zinc-100 p-1.5 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300"><flux:icon :name="$icon" variant="mini" class="size-4" /></span>
                </div>
                <p class="mt-2 text-3xl font-semibold tabular-nums">{{ $value }}</p>
                <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ $note }}</p>
            </div>
        @endforeach
    </section>

    <div class="grid items-start gap-4 xl:grid-cols-5">
        <section class="min-w-0 space-y-4 xl:col-span-3">
            <flux:heading size="lg">Agenda kunjungan</flux:heading>

            @if ($overdue->isNotEmpty())
                <flux:callout variant="warning" icon="exclamation-triangle" heading="{{ $overdue->count() }} kunjungan lewat belum diperbarui">
                    <flux:callout.text>Tandai hasilnya agar riwayat dan dashboard akurat.</flux:callout.text>
                </flux:callout>
            @endif

            @forelse ($overdue->isNotEmpty() ? collect(['overdue' => $overdue])->merge($agenda) : $agenda as $date => $items)
                <div>
                    <h3 class="mb-2 text-sm font-medium {{ $date === 'overdue' ? 'text-amber-700 dark:text-amber-400' : 'text-zinc-600 dark:text-zinc-300' }}">
                        {{ $date === 'overdue' ? 'Perlu diperbarui' : $dayLabel($date) }}
                    </h3>
                    <div class="divide-y divide-zinc-200 overflow-hidden rounded-xl border border-zinc-200 dark:divide-zinc-700 dark:border-zinc-700">
                        @foreach ($items as $visit)
                            @php($at = $visit->scheduled_at->timezone($tz))
                            @php($cover = $visit->listing->media->first())
                            <article wire:key="visit-{{ $visit->id }}" class="flex flex-col gap-3 p-3 sm:flex-row sm:items-center {{ $at->isToday() ? 'bg-sky-50/60 dark:bg-sky-950/20' : '' }}">
                                <div class="flex min-w-0 flex-1 items-center gap-3">
                                    <div class="w-16 shrink-0 text-center">
                                        <p class="text-lg font-semibold tabular-nums leading-tight">{{ $at->format('H:i') }}</p>
                                        <p class="text-xs text-zinc-500">{{ $date === 'overdue' ? $at->translatedFormat('j M') : 'WIB' }}</p>
                                    </div>
                                    @if ($cover)
                                        <img src="{{ $cover->url }}" alt="" loading="lazy" class="h-14 w-20 shrink-0 rounded-lg object-cover">
                                    @endif
                                    <div class="min-w-0">
                                        <p class="flex flex-wrap items-center gap-2">
                                            <a class="font-medium hover:underline" href="{{ route('prospects.show', $visit->prospect) }}" wire:navigate>{{ $visit->prospect->name }}</a>
                                            <flux:badge size="sm" :color="Prospect::STAGE_COLORS[$visit->prospect->stage] ?? 'zinc'">{{ ucfirst($visit->prospect->stage) }}</flux:badge>
                                        </p>
                                        <p class="truncate text-sm text-zinc-600 dark:text-zinc-300">{{ $visit->listing->title }}</p>
                                        <p class="flex flex-wrap gap-x-3 text-xs text-zinc-500">
                                            <span class="inline-flex items-center gap-1"><flux:icon.map-pin variant="micro" />{{ $visit->location ?: 'Lokasi belum dicatat' }}</span>
                                            @if ($user->isAdmin())<span class="inline-flex items-center gap-1"><flux:icon.user variant="micro" />{{ $visit->prospect->owner->name }}</span>@endif
                                        </p>
                                        @if ($visit->notes)<p class="mt-1 line-clamp-1 text-xs text-zinc-500">{{ $visit->notes }}</p>@endif
                                    </div>
                                </div>
                                @can('update', $visit->prospect)
                                    <div class="flex shrink-0 gap-2 sm:flex-col">
                                        <flux:button size="sm" icon="check" wire:click="markVisit({{ $visit->id }}, 'selesai')" wire:confirm="Tandai kunjungan {{ $visit->prospect->name }} selesai?">Selesai</flux:button>
                                        <flux:button size="sm" variant="ghost" icon="x-mark" wire:click="markVisit({{ $visit->id }}, 'dibatalkan')" wire:confirm="Batalkan kunjungan {{ $visit->prospect->name }}?">Batalkan</flux:button>
                                    </div>
                                @endcan
                            </article>
                        @endforeach
                    </div>
                </div>
            @empty
                <div class="rounded-xl border border-dashed border-zinc-300 p-8 text-center dark:border-zinc-600">
                    <flux:icon.calendar-days class="mx-auto text-zinc-400" />
                    <p class="mt-2 text-sm text-zinc-500">Belum ada kunjungan terjadwal. Jadwalkan dari halaman detail prospek.</p>
                </div>
            @endforelse
        </section>

        <section class="min-w-0 space-y-3 xl:col-span-2">
            <flux:heading size="lg">Tindak lanjut</flux:heading>
            <div class="rounded-xl border border-zinc-200 px-4 dark:border-zinc-700">
                <flux:table :paginate="$followups">
                    <flux:table.columns>
                        <flux:table.column>Prospek</flux:table.column>
                        <flux:table.column>Jadwal</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @forelse ($followups as $prospect)
                            <flux:table.row :key="'followup-'.$prospect->id">
                                <flux:table.cell variant="strong">
                                    <a class="hover:underline" href="{{ route('prospects.show', $prospect) }}" wire:navigate>{{ $prospect->name }}</a>
                                    <span class="block max-w-52 truncate text-xs font-normal text-zinc-500">{{ ucfirst($prospect->stage) }} · {{ $prospect->listing->title }}@if ($user->isAdmin()) · {{ $prospect->owner->name }}@endif</span>
                                </flux:table.cell>
                                <flux:table.cell>
                                    @if ($prospect->followup_on->lt($todayStart))
                                        <flux:badge size="sm" color="red">Terlambat · {{ $prospect->followup_on->format('d/m') }}</flux:badge>
                                    @elseif ($prospect->followup_on->isSameDay($todayStart))
                                        <flux:badge size="sm" color="amber">Hari ini</flux:badge>
                                    @else
                                        <span class="tabular-nums">{{ $prospect->followup_on->translatedFormat('D, j M') }}</span>
                                    @endif
                                </flux:table.cell>
                            </flux:table.row>
                        @empty
                            <flux:table.row><flux:table.cell colspan="2">Belum ada tindak lanjut terjadwal.</flux:table.cell></flux:table.row>
                        @endforelse
                    </flux:table.rows>
                </flux:table>
            </div>
        </section>
    </div>

    <section class="space-y-3">
        <flux:heading size="lg">Riwayat kunjungan</flux:heading>
        <div class="rounded-xl border border-zinc-200 px-4 dark:border-zinc-700">
            <flux:table :paginate="$history">
                <flux:table.columns>
                    <flux:table.column>Waktu</flux:table.column>
                    <flux:table.column>Prospek</flux:table.column>
                    <flux:table.column>Properti</flux:table.column>
                    @if ($user->isAdmin())<flux:table.column>Sales</flux:table.column>@endif
                    <flux:table.column>Hasil</flux:table.column>
                    <flux:table.column>Catatan</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @forelse ($history as $visit)
                        <flux:table.row :key="'history-'.$visit->id">
                            <flux:table.cell class="tabular-nums">{{ $visit->scheduled_at->timezone($tz)->translatedFormat('j M Y, H:i') }}</flux:table.cell>
                            <flux:table.cell variant="strong"><a class="hover:underline" href="{{ route('prospects.show', $visit->prospect) }}" wire:navigate>{{ $visit->prospect->name }}</a></flux:table.cell>
                            <flux:table.cell><span class="block max-w-56 truncate">{{ $visit->listing->title }}</span></flux:table.cell>
                            @if ($user->isAdmin())<flux:table.cell>{{ $visit->prospect->owner->name }}</flux:table.cell>@endif
                            <flux:table.cell><flux:badge size="sm" :color="$visit->status === 'selesai' ? 'green' : 'zinc'">{{ ucfirst($visit->status) }}</flux:badge></flux:table.cell>
                            <flux:table.cell><span class="block max-w-72 truncate">{{ $visit->notes ?: '–' }}</span></flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row><flux:table.cell colspan="6">Belum ada riwayat kunjungan.</flux:table.cell></flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </div>
    </section>
</div>
