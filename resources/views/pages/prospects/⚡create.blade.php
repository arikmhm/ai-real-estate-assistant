<?php

use App\Models\Listing;
use App\Models\Prospect;
use App\Models\User;
use App\Services\ProspectService;
use App\Support\ProspectPhone;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Tambah prospek')] class extends Component {
    public ?int $listingId = null;
    public ?int $ownerId = null;
    public string $name = '';
    public string $phone = '';
    public string $source = '';
    public string $stage = 'baru';
    public string $followupOn = '';
    public string $note = '';

    #[Locked]
    public ?string $duplicateFingerprint = null;

    public function mount(): void
    {
        Gate::authorize('create', Prospect::class);
    }

    public function save(ProspectService $service): void
    {
        Gate::authorize('create', Prospect::class);
        $this->duplicateFingerprint = null;
        $this->persist($service, false);
    }

    public function saveAnyway(ProspectService $service): void
    {
        Gate::authorize('create', Prospect::class);
        if ($this->duplicateFingerprint === null || $this->duplicateFingerprint !== $this->fingerprint()) {
            $this->addError('duplicate', 'Periksa ulang data dan tekan Simpan untuk melihat peringatan duplikasi.');
            return;
        }
        $this->persist($service, true);
    }

    private function persist(ProspectService $service, bool $allowDuplicate): void
    {
        $this->resetErrorBag();
        try {
            $prospect = $service->create(auth()->user(), $this->payload(), $allowDuplicate);
        } catch (ValidationException $exception) {
            if (isset($exception->errors()['duplicate'])) {
                $this->duplicateFingerprint = $this->fingerprint();
            }
            foreach ($exception->errors() as $field => $messages) {
                $this->addError(['listing_id' => 'listingId', 'owner_id' => 'ownerId', 'followup_on' => 'followupOn'][$field] ?? $field, $messages[0]);
            }
            return;
        }
        $this->redirect(route('prospects.show', $prospect), navigate: true);
    }

    private function fingerprint(): string
    {
        return hash('sha256', json_encode($this->payload()));
    }

    private function payload(): array
    {
        return ['listing_id' => $this->listingId, 'owner_id' => $this->ownerId, 'name' => $this->name,
            'phone' => $this->phone, 'source' => $this->source, 'stage' => $this->stage,
            'followup_on' => $this->followupOn ?: null, 'note' => $this->note];
    }
}; ?>

<div class="mx-auto max-w-3xl space-y-6 p-6">
    <flux:button :href="route('prospects.index')" variant="ghost" icon="arrow-left">Prospek</flux:button>
    <div><flux:heading size="xl">Tambah prospek</flux:heading><flux:text>Catat permintaan yang diterima di luar aplikasi.</flux:text></div>
    <form wire:submit="save" class="grid gap-4 rounded-xl border border-zinc-200 p-5 dark:border-zinc-700 md:grid-cols-2">
        <flux:select wire:model="listingId" label="Properti" required>
            <flux:select.option value="">Pilih properti</flux:select.option>
            @foreach (Listing::where('publication_status', 'published')->orderBy('title')->get() as $listing)
                <flux:select.option :value="(string) $listing->id">{{ $listing->title }} ({{ $listing->code }})</flux:select.option>
            @endforeach
        </flux:select>
        @if (auth()->user()->isAdmin())
            <flux:select wire:model="ownerId" label="Sales penanggung jawab" required>
                <flux:select.option value="">Pilih sales aktif</flux:select.option>
                @foreach (User::activeSales()->orderBy('name')->get() as $sales)<flux:select.option :value="(string) $sales->id">{{ $sales->name }}</flux:select.option>@endforeach
            </flux:select>
        @endif
        <flux:input wire:model="name" label="Nama prospek" required />
        <flux:input wire:model="phone" label="Nomor ponsel (08 atau +62)" required />
        <flux:input wire:model="source" label="Sumber kontak" placeholder="Contoh: WhatsApp" required />
        <flux:select wire:model="stage" label="Tahap">
            @foreach (Prospect::STAGES as $value)<flux:select.option :value="$value">{{ ucfirst($value) }}</flux:select.option>@endforeach
        </flux:select>
        <flux:input wire:model="followupOn" label="Tanggal tindak lanjut (opsional)" type="date" />
        <div class="md:col-span-2"><flux:textarea wire:model="note" label="Catatan awal (opsional)" rows="4" /></div>
        @error('duplicate')<p role="alert" class="text-sm text-amber-700 md:col-span-2">{{ $message }}</p>@enderror
        @if ($duplicateFingerprint && auth()->user()->isAdmin() && ($duplicatePhone = ProspectPhone::normalize($phone)) && $listingId)
            <div class="space-y-1 text-sm md:col-span-2">
                <p class="font-medium">Prospek yang cocok:</p>
                @foreach (Prospect::where('listing_id', $listingId)->where('phone_e164', $duplicatePhone)->with('owner')->orderBy('id')->get() as $match)
                    <p><a class="underline" href="{{ route('prospects.show', $match) }}">{{ $match->name }}</a> · {{ $match->owner->name }}</p>
                @endforeach
            </div>
        @endif
        <div class="flex flex-wrap gap-3 md:col-span-2">
            <flux:button type="submit" variant="primary">Simpan prospek</flux:button>
            @if ($duplicateFingerprint)<flux:button type="button" wire:click="saveAnyway">Simpan tetap</flux:button>@endif
        </div>
    </form>
</div>
