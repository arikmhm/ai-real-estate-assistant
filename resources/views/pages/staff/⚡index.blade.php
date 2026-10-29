<?php

use App\Enums\UserRole;
use App\Models\User;
use Flux\Flux;
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
            'newWhatsAppNumber' => ['nullable', 'regex:/^\+[1-9]\d{7,14}$/'],
            'newContactEmail' => ['nullable', 'email', 'max:255'],
            'newPassword' => ['required', Password::default(), 'same:newPasswordConfirmation'],
            'newPasswordConfirmation' => ['required'],
        ]);

        User::create([
            'name' => $data['newName'],
            'email' => $data['newEmail'],
            'whatsapp_number' => $data['newWhatsAppNumber'] ?: null,
            'contact_email' => $data['newContactEmail'] ?: null,
            'password' => $data['newPassword'],
            'role' => UserRole::Sales,
            'is_active' => true,
            'public_contact_approved' => false,
        ]);

        $this->reset('newName', 'newEmail', 'newWhatsAppNumber', 'newContactEmail', 'newPassword', 'newPasswordConfirmation');
        Flux::modal('create-sales')->close();
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
        Flux::modal('edit-sales')->show();
    }

    public function updateSales(): void
    {
        $sales = User::findOrFail($this->editingSalesId);
        Gate::authorize('update', $sales);
        $this->editEmail = Str::lower(trim($this->editEmail));

        $data = $this->validate([
            'editName' => ['required', 'string', 'max:255'],
            'editEmail' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($sales->id)],
            'editWhatsAppNumber' => ['nullable', 'regex:/^\+[1-9]\d{7,14}$/'],
            'editContactEmail' => ['nullable', 'email', 'max:255'],
        ]);

        $emailChanged = $sales->email !== $data['editEmail'];
        $contactChanged = $sales->whatsapp_number !== ($data['editWhatsAppNumber'] ?: null)
            || $sales->contact_email !== ($data['editContactEmail'] ?: null);
        $sales->fill([
            'name' => $data['editName'],
            'email' => $data['editEmail'],
            'whatsapp_number' => $data['editWhatsAppNumber'] ?: null,
            'contact_email' => $data['editContactEmail'] ?: null,
        ]);
        if ($emailChanged) {
            $sales->email_verified_at = null;
        }
        if ($contactChanged) {
            $sales->public_contact_approved = false;
        }
        $sales->save();

        $this->editingSalesId = null;
        Flux::modal('edit-sales')->close();
        session()->flash('status', 'Profil sales diperbarui.');
    }

    public function cancelEdit(): void
    {
        Gate::authorize('viewAny', User::class);
        $this->editingSalesId = null;
        Flux::modal('edit-sales')->close();
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

    public function toggleContactApproval(int $salesId): void
    {
        $sales = User::findOrFail($salesId);
        Gate::authorize('update', $sales);

        if (! $sales->public_contact_approved && ! preg_match('/^\+[1-9]\d{7,14}$/', $sales->whatsapp_number ?? '')) {
            $this->addError('contactApproval', 'Isi nomor WhatsApp valid sebelum menyetujui kontak publik.');

            return;
        }

        $sales->update(['public_contact_approved' => ! $sales->public_contact_approved]);
        session()->flash('status', $sales->public_contact_approved ? 'Kontak sales dipublikasikan.' : 'Publikasi kontak sales dicabut.');
    }
}; ?>

<div class="mx-auto max-w-7xl space-y-6 p-4 sm:p-6">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <flux:heading size="xl">Staf sales</flux:heading>
            <flux:text>Kelola akun dan kontak sales yang akan ditampilkan kepada pengunjung.</flux:text>
        </div>
        <flux:modal.trigger name="create-sales">
            <flux:button variant="primary" icon="plus">Tambah sales</flux:button>
        </flux:modal.trigger>
    </div>

    @if (session('status'))
        <flux:callout variant="success">{{ session('status') }}</flux:callout>
    @endif
    @error('contactApproval')<flux:callout variant="danger" role="alert">{{ $message }}</flux:callout>@enderror

    @php($salesList = User::where('role', UserRole::Sales->value)->withCount([
        'ownedProspects as active_count' => fn ($query) => $query->whereNotIn('stage', ['berhasil', 'gagal']),
        'ownedProspects as won_count' => fn ($query) => $query->whereIn('stage', ['booking', 'berhasil']),
    ])->orderBy('name')->orderBy('id')->get())
    <div class="rounded-xl border border-zinc-200 px-4 dark:border-zinc-700">
        <flux:table>
            <flux:table.columns>
                <flux:table.column>Sales</flux:table.column>
                <flux:table.column>WhatsApp</flux:table.column>
                <flux:table.column>Email kontak</flux:table.column>
                <flux:table.column>Kontak publik</flux:table.column>
                <flux:table.column>Status</flux:table.column>
                <flux:table.column align="end">Prospek aktif</flux:table.column>
                <flux:table.column align="end">Booking/berhasil</flux:table.column>
                <flux:table.column align="end"><span class="sr-only">Aksi</span></flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($salesList as $sales)
                    <flux:table.row :key="$sales->id">
                        <flux:table.cell variant="strong">
                            <div class="flex items-center gap-3">
                                <flux:avatar size="sm" :name="$sales->name" :initials="$sales->initials()" :src="$sales->avatar_path ? asset($sales->avatar_path) : null" />
                                <div class="min-w-0">
                                    {{ $sales->name }}
                                    <span class="block max-w-40 truncate text-xs font-normal text-zinc-500" title="{{ $sales->email }}">{{ $sales->email }}</span>
                                </div>
                            </div>
                        </flux:table.cell>
                        <flux:table.cell class="tabular-nums">{{ $sales->whatsapp_number ?: 'Belum diisi' }}</flux:table.cell>
                        <flux:table.cell><span class="block max-w-44 truncate" title="{{ $sales->contact_email }}">{{ $sales->contact_email ?: '–' }}</span></flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="$sales->public_contact_approved ? 'green' : 'zinc'">{{ $sales->public_contact_approved ? 'Disetujui' : 'Belum disetujui' }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="$sales->is_active ? 'sky' : 'red'">{{ $sales->is_active ? 'Aktif' : 'Nonaktif' }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell align="end" class="tabular-nums">{{ $sales->active_count }}</flux:table.cell>
                        <flux:table.cell align="end" class="tabular-nums">{{ $sales->won_count }}</flux:table.cell>
                        <flux:table.cell align="end">
                            <flux:dropdown position="bottom" align="end">
                                <flux:button size="sm" variant="ghost" icon="ellipsis-horizontal" aria-label="Aksi untuk {{ $sales->name }}" />
                                <flux:menu>
                                    <flux:menu.item icon="pencil-square" wire:click="startEdit({{ $sales->id }})">Ubah profil</flux:menu.item>
                                    <flux:menu.item icon="globe-alt" wire:click="toggleContactApproval({{ $sales->id }})" :disabled="! $sales->public_contact_approved && ! $sales->whatsapp_number">
                                        {{ $sales->public_contact_approved ? 'Cabut kontak publik' : 'Setujui kontak publik' }}
                                    </flux:menu.item>
                                    <flux:menu.separator />
                                    <flux:menu.item :icon="$sales->is_active ? 'no-symbol' : 'check-circle'" :variant="$sales->is_active ? 'danger' : 'default'" wire:click="toggleActive({{ $sales->id }})">
                                        {{ $sales->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                                    </flux:menu.item>
                                </flux:menu>
                            </flux:dropdown>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row><flux:table.cell colspan="8">Belum ada akun sales.</flux:table.cell></flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>

    <flux:modal name="create-sales" class="w-full max-w-2xl">
        <flux:heading size="lg">Tambah sales</flux:heading>
        <form wire:submit="createSales" class="mt-5 grid gap-4 md:grid-cols-2">
            <flux:input wire:model="newName" label="Nama" required />
            <flux:input wire:model="newEmail" label="Email login" type="email" required />
            <flux:input wire:model="newWhatsAppNumber" label="WhatsApp (+628...)" />
            <flux:input wire:model="newContactEmail" label="Email kontak publik (opsional)" type="email" />
            <flux:input wire:model="newPassword" label="Kata sandi sementara" type="password" required />
            <flux:input wire:model="newPasswordConfirmation" label="Ulangi kata sandi" type="password" required />
            <div class="flex justify-end gap-2 md:col-span-2">
                <flux:modal.close><flux:button type="button" variant="ghost">Batal</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">Buat akun sales</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="edit-sales" class="w-full max-w-2xl" @close="cancelEdit">
        <flux:heading size="lg">Ubah profil sales</flux:heading>
        <form wire:submit="updateSales" class="mt-5 grid gap-4 md:grid-cols-2">
            <flux:input wire:model="editName" label="Nama" required />
            <flux:input wire:model="editEmail" label="Email login" type="email" required />
            <flux:input wire:model="editWhatsAppNumber" label="WhatsApp (+628...)" />
            <flux:input wire:model="editContactEmail" label="Email kontak publik (opsional)" type="email" />
            <div class="flex justify-end gap-2 md:col-span-2">
                <flux:button type="button" variant="ghost" wire:click="cancelEdit">Batal</flux:button>
                <flux:button type="submit" variant="primary">Simpan</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
