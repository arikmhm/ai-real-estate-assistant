<?php

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Staf sales')] class extends Component {
    public string $newName = '';
    public string $newEmail = '';
    public string $newWhatsAppNumber = '';
    public string $newContactEmail = '';
    public string $newPassword = '';
    public string $newPasswordConfirmation = '';

    #[Locked]
    public ?int $editingSalesId = null;
    public string $editName = '';
    public string $editEmail = '';
    public string $editWhatsAppNumber = '';
    public string $editContactEmail = '';

    public function mount(): void
    {
        Gate::authorize('viewAny', User::class);
    }

    public function createSales(): void
    {
        Gate::authorize('create', User::class);
        $this->newEmail = Str::lower(trim($this->newEmail));

        $data = $this->validate([
            'newName' => ['required', 'string', 'max:255'],
            'newEmail' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'newWhatsAppNumber' => ['required', 'regex:/^\+[1-9]\d{7,14}$/'],
            'newContactEmail' => ['nullable', 'email', 'max:255'],
            'newPassword' => ['required', Password::default(), 'same:newPasswordConfirmation'],
            'newPasswordConfirmation' => ['required'],
        ]);

        User::create([
            'name' => $data['newName'],
            'email' => $data['newEmail'],
            'whatsapp_number' => $data['newWhatsAppNumber'],
            'contact_email' => $data['newContactEmail'] ?: null,
            'password' => $data['newPassword'],
            'role' => UserRole::Sales,
            'is_active' => true,
        ]);

        $this->reset('newName', 'newEmail', 'newWhatsAppNumber', 'newContactEmail', 'newPassword', 'newPasswordConfirmation');
        session()->flash('status', 'Akun sales dibuat. Sampaikan kata sandi sementara di luar aplikasi.');
    }

    public function startEdit(int $salesId): void
    {
        $sales = User::findOrFail($salesId);
        Gate::authorize('update', $sales);

        $this->editingSalesId = $sales->id;
        $this->editName = $sales->name;
        $this->editEmail = $sales->email;
        $this->editWhatsAppNumber = $sales->whatsapp_number ?? '';
        $this->editContactEmail = $sales->contact_email ?? '';
    }

    public function updateSales(): void
    {
        $sales = User::findOrFail($this->editingSalesId);
        Gate::authorize('update', $sales);
        $this->editEmail = Str::lower(trim($this->editEmail));

        $data = $this->validate([
            'editName' => ['required', 'string', 'max:255'],
            'editEmail' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($sales->id)],
            'editWhatsAppNumber' => ['required', 'regex:/^\+[1-9]\d{7,14}$/'],
            'editContactEmail' => ['nullable', 'email', 'max:255'],
        ]);

        $emailChanged = $sales->email !== $data['editEmail'];
        $sales->fill([
            'name' => $data['editName'],
            'email' => $data['editEmail'],
            'whatsapp_number' => $data['editWhatsAppNumber'],
            'contact_email' => $data['editContactEmail'] ?: null,
        ]);
        if ($emailChanged) {
            $sales->email_verified_at = null;
        }
        $sales->save();

        $this->editingSalesId = null;
        session()->flash('status', 'Profil sales diperbarui.');
    }

    public function cancelEdit(): void
    {
        Gate::authorize('viewAny', User::class);
        $this->editingSalesId = null;
    }

    public function toggleActive(int $salesId): void
    {
        $sales = User::findOrFail($salesId);
        Gate::authorize('update', $sales);

        $sales->update(['is_active' => ! $sales->is_active]);
        if (! $sales->is_active) {
            DB::table('sessions')->where('user_id', $sales->id)->delete();
        }

        session()->flash('status', $sales->is_active ? 'Akun sales diaktifkan.' : 'Akun sales dinonaktifkan.');
    }
}; ?>

<div class="mx-auto max-w-5xl space-y-8 p-6">
    <div>
        <flux:heading size="xl">Staf sales</flux:heading>
        <flux:text>Kelola akun dan kontak sales yang akan ditampilkan kepada pengunjung.</flux:text>
    </div>

    @if (session('status'))
        <flux:callout variant="success">{{ session('status') }}</flux:callout>
    @endif

    <section class="rounded-xl border border-zinc-200 p-6 dark:border-zinc-700">
        <flux:heading size="lg">Tambah sales</flux:heading>
        <form wire:submit="createSales" class="mt-5 grid gap-4 md:grid-cols-2">
            <flux:input wire:model="newName" label="Nama" required />
            <flux:input wire:model="newEmail" label="Email login" type="email" required />
            <flux:input wire:model="newWhatsAppNumber" label="WhatsApp (+628...)" required />
            <flux:input wire:model="newContactEmail" label="Email kontak publik (opsional)" type="email" />
            <flux:input wire:model="newPassword" label="Kata sandi sementara" type="password" required />
            <flux:input wire:model="newPasswordConfirmation" label="Ulangi kata sandi" type="password" required />
            <div class="md:col-span-2"><flux:button type="submit" variant="primary">Buat akun sales</flux:button></div>
        </form>
    </section>

    @if ($editingSalesId)
        <section class="rounded-xl border border-zinc-200 p-6 dark:border-zinc-700">
            <flux:heading size="lg">Ubah profil sales</flux:heading>
            <form wire:submit="updateSales" class="mt-5 grid gap-4 md:grid-cols-2">
                <flux:input wire:model="editName" label="Nama" required />
                <flux:input wire:model="editEmail" label="Email login" type="email" required />
                <flux:input wire:model="editWhatsAppNumber" label="WhatsApp (+628...)" required />
                <flux:input wire:model="editContactEmail" label="Email kontak publik (opsional)" type="email" />
                <div class="flex gap-3 md:col-span-2">
                    <flux:button type="submit" variant="primary">Simpan</flux:button>
                    <flux:button type="button" wire:click="cancelEdit">Batal</flux:button>
                </div>
            </form>
        </section>
    @endif

    <section class="space-y-3">
        <flux:heading size="lg">Daftar sales</flux:heading>
        @forelse (User::where('role', UserRole::Sales->value)->orderBy('name')->get() as $sales)
            <div wire:key="sales-{{ $sales->id }}" class="flex flex-wrap items-center justify-between gap-4 rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                <div>
                    <div class="font-semibold">{{ $sales->name }} <span class="text-sm font-normal text-zinc-500">{{ $sales->is_active ? 'Aktif' : 'Nonaktif' }}</span></div>
                    <div class="text-sm text-zinc-600 dark:text-zinc-300">{{ $sales->email }} · {{ $sales->whatsapp_number ?: 'WhatsApp belum diisi' }}</div>
                    @if ($sales->contact_email)<div class="text-sm text-zinc-500">{{ $sales->contact_email }}</div>@endif
                </div>
                <div class="flex gap-2">
                    <flux:button wire:click="startEdit({{ $sales->id }})">Ubah</flux:button>
                    <flux:button wire:click="toggleActive({{ $sales->id }})" :variant="$sales->is_active ? 'danger' : 'primary'">
                        {{ $sales->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                    </flux:button>
                </div>
            </div>
        @empty
            <flux:text>Belum ada akun sales.</flux:text>
        @endforelse
    </section>
</div>
