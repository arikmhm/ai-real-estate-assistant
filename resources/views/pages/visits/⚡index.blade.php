<?php

use App\Models\Prospect;
use App\Models\Visit;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Kunjungan dan tindak lanjut')] class extends Component {
    use WithPagination;

    public function mount(): void
    {
        Gate::authorize('viewAny', Prospect::class);
    }
}; ?>

<div class="mx-auto max-w-6xl space-y-7 p-6">
    <div><flux:heading size="xl">Kunjungan dan tindak lanjut</flux:heading><flux:text>{{ auth()->user()->isAdmin() ? 'Seluruh agenda agensi.' : 'Agenda prospek yang Anda tangani.' }}</flux:text></div>
    @php($visits = Visit::with(['prospect.owner', 'listing'])->whereHas('prospect', fn ($query) => $query->visibleTo(auth()->user()))->orderByDesc('scheduled_at')->paginate(12, ['*'], 'visitsPage'))
    <section class="space-y-3">
        <flux:heading size="lg">Kunjungan</flux:heading>
        @forelse ($visits as $visit)
            <article class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                <p class="font-medium">{{ $visit->scheduled_at->timezone('Asia/Jakarta')->format('d/m/Y H:i') }} WIB · {{ ucfirst($visit->status) }}</p>
                <p><a class="underline" href="{{ route('prospects.show', $visit->prospect) }}">{{ $visit->prospect->name }}</a> · {{ $visit->listing->title }}</p>
                <p class="text-sm text-zinc-500">{{ $visit->location ?: 'Lokasi belum dicatat' }} · {{ $visit->prospect->owner->name }}</p>
            </article>
        @empty
            <p>Belum ada kunjungan.</p>
        @endforelse
        {{ $visits->links() }}
    </section>
    @php($followups = Prospect::visibleTo(auth()->user())->with(['listing', 'owner'])->whereNotNull('followup_on')->whereNotIn('stage', ['berhasil', 'gagal'])->orderBy('followup_on')->paginate(12, ['*'], 'followupsPage'))
    <section class="space-y-3">
        <flux:heading size="lg">Tanggal tindak lanjut</flux:heading>
        @forelse ($followups as $prospect)
            <article class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                <p class="font-medium">{{ $prospect->followup_on->format('d/m/Y') }} · {{ ucfirst($prospect->stage) }}</p>
                <p><a class="underline" href="{{ route('prospects.show', $prospect) }}">{{ $prospect->name }}</a> · {{ $prospect->listing->title }}</p>
                <p class="text-sm text-zinc-500">{{ $prospect->owner->name }}</p>
            </article>
        @empty
            <p>Belum ada tindak lanjut terjadwal.</p>
        @endforelse
        {{ $followups->links() }}
    </section>
</div>
