<?php

use App\Models\Prospect;
use App\Models\User;
use App\Models\Visit;
use App\Services\ProspectService;
use App\Services\VisitService;
use App\Support\ProspectPhone;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Detail prospek')] class extends Component {
    #[Locked]
    public int $prospectId;
    #[Locked]
    public ?int $editingVisitId = null;
    #[Locked]
    public ?string $duplicateFingerprint = null;

    public string $editName = '';
    public string $editPhone = '';
    public string $editSource = '';
    public string $editFollowupOn = '';
    public string $newStage = 'baru';
    public ?int $newOwnerId = null;
    public string $activityType = 'note';
    public string $activityBody = '';
    public string $visitAt = '';
    public string $visitStatus = 'dijadwalkan';
    public string $visitLocation = '';
    public string $visitNotes = '';

    public function mount(Prospect $prospect): void
    {
        Gate::authorize('view', $prospect);
        $this->prospectId = $prospect->id;
        $this->editName = $prospect->name;
        $this->editPhone = $prospect->phone_e164;
        $this->editSource = $prospect->source;
        $this->editFollowupOn = $prospect->followup_on?->format('Y-m-d') ?? '';
        $this->newStage = $prospect->stage;
        $this->newOwnerId = $prospect->owner->is_active ? $prospect->owner_id : null;
    }

    private function current(): Prospect
    {
        $prospect = Prospect::findOrFail($this->prospectId);
        Gate::authorize('view', $prospect);
        return $prospect;
    }

    public function saveDetails(ProspectService $service): void
    {
        $prospect = $this->current();
        $this->duplicateFingerprint = null;
        $this->persistDetails($service, $prospect, false);
    }

    public function saveDetailsAnyway(ProspectService $service): void
    {
        $prospect = $this->current();
        if ($this->duplicateFingerprint === null || $this->duplicateFingerprint !== $this->detailsFingerprint()) {
            $this->addError('duplicate', 'Periksa ulang data dan simpan untuk melihat peringatan duplikasi.');
            return;
        }
        $this->persistDetails($service, $prospect, true);
    }

    private function persistDetails(ProspectService $service, Prospect $prospect, bool $allowDuplicate): void
    {
        $this->resetErrorBag();
        try {
            $service->updateDetails(auth()->user(), $prospect, $this->detailsPayload(), $allowDuplicate);
        } catch (ValidationException $exception) {
            if (isset($exception->errors()['duplicate'])) {
                $this->duplicateFingerprint = $this->detailsFingerprint();
            }
            $this->showValidation($exception, ['name' => 'editName', 'phone' => 'editPhone', 'source' => 'editSource', 'followup_on' => 'editFollowupOn']);
            return;
        }
        $this->duplicateFingerprint = null;
        session()->flash('status', 'Data prospek diperbarui.');
    }

    private function detailsPayload(): array
    {
        return ['name' => $this->editName, 'phone' => $this->editPhone,
            'source' => $this->editSource, 'followup_on' => $this->editFollowupOn ?: null];
    }

    private function detailsFingerprint(): string
    {
        return hash('sha256', json_encode($this->detailsPayload()));
    }

    public function changeStage(ProspectService $service): void
    {
        try {
            $service->changeStage(auth()->user(), $this->current(), $this->newStage);
        } catch (ValidationException $exception) {
            $this->showValidation($exception, ['stage' => 'newStage']);
            return;
        }
        session()->flash('status', 'Tahap prospek diperbarui.');
    }

    public function reassign(ProspectService $service): void
    {
        $prospect = $this->current();
        Gate::authorize('reassign', $prospect);
        try {
            $service->reassign(auth()->user(), $prospect, (int) $this->newOwnerId);
        } catch (ValidationException $exception) {
            $this->showValidation($exception, ['owner_id' => 'newOwnerId']);
            return;
        }
        session()->flash('status', 'Penanggung jawab diperbarui.');
    }

    public function addActivity(ProspectService $service): void
    {
        try {
            $service->addActivity(auth()->user(), $this->current(), $this->activityType, $this->activityBody);
        } catch (ValidationException $exception) {
            $this->showValidation($exception, ['activity' => 'activityBody']);
            return;
        }
        $this->activityBody = '';
        session()->flash('status', 'Aktivitas dicatat.');
    }

    public function scheduleVisit(VisitService $service): void
    {
        try {
            $service->create(auth()->user(), $this->current(), [
                'scheduled_at' => $this->visitAt, 'location' => $this->visitLocation,
                'notes' => $this->visitNotes,
            ]);
        } catch (ValidationException $exception) {
            $this->showValidation($exception, ['scheduled_at' => 'visitAt', 'location' => 'visitLocation', 'notes' => 'visitNotes', 'status' => 'visitStatus']);
            return;
        }
        $this->reset('visitAt', 'visitLocation', 'visitNotes');
        session()->flash('status', 'Kunjungan dijadwalkan.');
    }

    public function startVisitEdit(int $visitId): void
    {
        $prospect = $this->current();
        Gate::authorize('update', $prospect);
        $visit = $prospect->visits()->findOrFail($visitId);
        $this->editingVisitId = $visit->id;
        $this->visitAt = $visit->scheduled_at->timezone('Asia/Jakarta')->format('Y-m-d\TH:i');
        $this->visitStatus = $visit->status;
        $this->visitLocation = $visit->location ?? '';
        $this->visitNotes = $visit->notes ?? '';
    }

    public function updateVisit(VisitService $service): void
    {
        $prospect = $this->current();
        Gate::authorize('update', $prospect);
        $visit = $prospect->visits()->findOrFail($this->editingVisitId);
        try {
            $service->update(auth()->user(), $visit, [
                'scheduled_at' => $this->visitAt, 'status' => $this->visitStatus,
                'location' => $this->visitLocation, 'notes' => $this->visitNotes,
            ]);
        } catch (ValidationException $exception) {
            $this->showValidation($exception, ['scheduled_at' => 'visitAt', 'location' => 'visitLocation', 'notes' => 'visitNotes', 'status' => 'visitStatus']);
            return;
        }
        $this->editingVisitId = null;
        $this->reset('visitAt', 'visitLocation', 'visitNotes');
        $this->visitStatus = 'dijadwalkan';
        session()->flash('status', 'Kunjungan diperbarui.');
    }

    public function cancelVisitEdit(): void
    {
        Gate::authorize('update', $this->current());
        $this->editingVisitId = null;
        $this->reset('visitAt', 'visitLocation', 'visitNotes');
        $this->visitStatus = 'dijadwalkan';
    }

    private function showValidation(ValidationException $exception, array $mapping): void
    {
        foreach ($exception->errors() as $field => $messages) {
            $this->addError($mapping[$field] ?? $field, $messages[0]);
        }
    }
}; ?>

@php($prospect = Prospect::visibleTo(auth()->user())->with(['listing', 'owner', 'activities.actor', 'visits'])->findOrFail($prospectId))
<div class="mx-auto max-w-5xl space-y-7 p-6">
    <flux:button :href="route('prospects.index')" variant="ghost">← Prospek</flux:button>
    @if (session('status'))<flux:callout variant="success">{{ session('status') }}</flux:callout>@endif
    <div><flux:heading size="xl">{{ $prospect->name }}</flux:heading><flux:text>{{ $prospect->phone_e164 }} · {{ $prospect->source }}</flux:text></div>
    <div class="grid gap-4 rounded-xl border border-zinc-200 p-5 dark:border-zinc-700 md:grid-cols-2">
        <div><span class="text-sm text-zinc-500">Properti</span><p><a class="font-medium underline" href="{{ route('listings.show', $prospect->listing) }}">{{ $prospect->listing->title }}</a> ({{ $prospect->listing->code }})</p></div>
        <div><span class="text-sm text-zinc-500">Penanggung jawab</span><p>{{ $prospect->owner->name }}{{ $prospect->owner->is_active ? '' : ' (nonaktif)' }}</p></div>
        <div><span class="text-sm text-zinc-500">Tahap</span><p>{{ ucfirst($prospect->stage) }}</p></div>
        <div><span class="text-sm text-zinc-500">Tindak lanjut</span><p>{{ $prospect->followup_on?->format('d/m/Y') ?? 'Belum dijadwalkan' }}</p></div>
    </div>

    <section class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
        <flux:heading size="lg">Perbarui data</flux:heading>
        <form wire:submit="saveDetails" class="mt-4 grid gap-4 md:grid-cols-2">
            <flux:input wire:model="editName" label="Nama" required />
            <flux:input wire:model="editPhone" label="Nomor ponsel" required />
            <flux:input wire:model="editSource" label="Sumber" required />
            <flux:input wire:model="editFollowupOn" label="Tanggal tindak lanjut" type="date" />
            @error('duplicate')<p role="alert" class="text-sm text-amber-700 md:col-span-2">{{ $message }}</p>@enderror
            @if ($duplicateFingerprint && auth()->user()->isAdmin() && ($duplicatePhone = ProspectPhone::normalize($editPhone)))
                <div class="space-y-1 text-sm md:col-span-2">
                    <p class="font-medium">Prospek yang cocok:</p>
                    @foreach (Prospect::where('listing_id', $prospect->listing_id)->where('phone_e164', $duplicatePhone)->whereKeyNot($prospect->id)->with('owner')->orderBy('id')->get() as $match)
                        <p><a class="underline" href="{{ route('prospects.show', $match) }}">{{ $match->name }}</a> · {{ $match->owner->name }}</p>
                    @endforeach
                </div>
            @endif
            <div class="flex gap-3 md:col-span-2"><flux:button type="submit">Simpan data</flux:button>@if ($duplicateFingerprint)<flux:button type="button" wire:click="saveDetailsAnyway">Simpan tetap</flux:button>@endif</div>
        </form>
    </section>

    <div class="grid gap-5 md:grid-cols-2">
        <section class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
            <flux:heading size="lg">Tahap penjualan</flux:heading>
            <form wire:submit="changeStage" class="mt-4 space-y-3">
                <flux:select wire:model="newStage" label="Tahap">
                    @foreach (Prospect::STAGES as $value)<flux:select.option :value="$value">{{ ucfirst($value) }}</flux:select.option>@endforeach
                </flux:select>
                <flux:button type="submit">Perbarui tahap</flux:button>
            </form>
        </section>
        @if (auth()->user()->isAdmin())
            <section class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
                <flux:heading size="lg">Alihkan penanggung jawab</flux:heading>
                <form wire:submit="reassign" class="mt-4 space-y-3">
                    <flux:select wire:model="newOwnerId" label="Sales aktif">
                        <flux:select.option value="">Pilih sales aktif</flux:select.option>
                        @foreach (User::activeSales()->orderBy('name')->get() as $sales)<flux:select.option :value="(string) $sales->id">{{ $sales->name }}</flux:select.option>@endforeach
                    </flux:select>
                    <flux:button type="submit">Alihkan</flux:button>
                </form>
            </section>
        @endif
    </div>

    <section class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
        <flux:heading size="lg">Catatan dan tindak lanjut</flux:heading>
        <form wire:submit="addActivity" class="mt-4 space-y-3">
            <flux:select wire:model="activityType" label="Jenis aktivitas"><flux:select.option value="note">Catatan</flux:select.option><flux:select.option value="followup">Tindak lanjut</flux:select.option></flux:select>
            <flux:textarea wire:model="activityBody" label="Isi" rows="3" required />
            <flux:button type="submit">Catat aktivitas</flux:button>
        </form>
        <div class="mt-6 space-y-3">
            @forelse ($prospect->activities->reverse() as $activity)
                <article class="rounded-lg bg-zinc-50 p-3 dark:bg-zinc-800">
                    <p class="text-sm font-medium">{{ ucfirst(str_replace('_', ' ', $activity->type)) }} · {{ $activity->actor->name }} · {{ $activity->created_at?->timezone('Asia/Jakarta')->format('d/m/Y H:i') }}</p>
                    @if ($activity->body)<p class="mt-1 whitespace-pre-line text-sm">{{ $activity->body }}</p>@endif
                    @if ($activity->type === 'stage_changed')<p class="text-xs text-zinc-500">{{ ucfirst($activity->details['from']) }} → {{ ucfirst($activity->details['to']) }}</p>@endif
                    @if ($activity->type === 'owner_changed')<p class="text-xs text-zinc-500">{{ $activity->details['from_name'] ?? 'Sales #'.$activity->details['from'] }} → {{ $activity->details['to_name'] ?? 'Sales #'.$activity->details['to'] }}</p>@endif
                </article>
            @empty
                <p class="text-sm text-zinc-500">Belum ada aktivitas.</p>
            @endforelse
        </div>
    </section>

    <section class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
        <flux:heading size="lg">Kunjungan</flux:heading>
        <form wire:submit="{{ $editingVisitId ? 'updateVisit' : 'scheduleVisit' }}" class="mt-4 grid gap-3 md:grid-cols-2">
            <flux:input wire:model="visitAt" label="Waktu (WIB)" type="datetime-local" required />
            @if ($editingVisitId)
                <flux:select wire:model="visitStatus" label="Status">
                    @foreach (Visit::STATUSES as $status)<flux:select.option :value="$status">{{ ucfirst($status) }}</flux:select.option>@endforeach
                </flux:select>
            @endif
            <flux:input wire:model="visitLocation" label="Lokasi (opsional)" />
            <div class="md:col-span-2"><flux:textarea wire:model="visitNotes" label="Catatan (opsional)" rows="3" /></div>
            <div class="flex gap-3 md:col-span-2"><flux:button type="submit">{{ $editingVisitId ? 'Perbarui kunjungan' : 'Jadwalkan kunjungan' }}</flux:button>@if ($editingVisitId)<flux:button type="button" wire:click="cancelVisitEdit">Batal ubah</flux:button>@endif</div>
        </form>
        <div class="mt-6 space-y-3">
            @forelse ($prospect->visits as $visit)
                <article wire:key="visit-{{ $visit->id }}" class="flex flex-wrap items-center justify-between gap-3 rounded-lg bg-zinc-50 p-3 dark:bg-zinc-800">
                    <div><p class="font-medium">{{ $visit->scheduled_at->timezone('Asia/Jakarta')->format('d/m/Y H:i') }} WIB · {{ ucfirst($visit->status) }}</p><p class="text-sm">{{ $visit->location ?: 'Lokasi belum dicatat' }}</p>@if ($visit->notes)<p class="text-sm text-zinc-600">{{ $visit->notes }}</p>@endif</div>
                    <flux:button size="sm" wire:click="startVisitEdit({{ $visit->id }})">Ubah</flux:button>
                </article>
            @empty
                <p class="text-sm text-zinc-500">Belum ada kunjungan.</p>
            @endforelse
        </div>
    </section>
</div>
