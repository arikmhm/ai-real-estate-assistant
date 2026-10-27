<?php

use App\Models\Listing;
use App\Models\ListingMedia;
use App\Services\ListingMediaService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('Editor properti')] class extends Component {
    use WithFileUploads;

    #[Locked]
    public ?int $listingId = null;

    public array $newPhotos = [];
    public $newFloorplan = null;

    public string $title = '';
    public string $type = 'rumah';
    public string $priceIdr = '';
    public string $province = '';
    public string $city = '';
    public string $district = '';
    public string $address = '';
    public string $latitude = '';
    public string $longitude = '';
    public string $landAreaM2 = '';
    public string $buildingAreaM2 = '';
    public string $rooms = '';
    public string $bathrooms = '';
    public string $facilitiesText = '';
    public string $description = '';

    public function mount(?Listing $listing = null): void
    {
        Gate::authorize($listing ? 'update' : 'create', $listing ?? Listing::class);
        if (! $listing) return;

        $this->listingId = $listing->id;
        foreach (['title', 'type', 'province', 'city', 'district', 'address', 'description'] as $field) {
            $this->$field = (string) $listing->$field;
        }
        foreach (['priceIdr' => 'price_idr', 'latitude' => 'latitude', 'longitude' => 'longitude', 'landAreaM2' => 'land_area_m2', 'buildingAreaM2' => 'building_area_m2', 'rooms' => 'rooms', 'bathrooms' => 'bathrooms'] as $property => $field) {
            $this->$property = (string) ($listing->$field ?? '');
        }
        $this->facilitiesText = implode(', ', $listing->facilities ?? []);
    }

    private function currentListing(): Listing
    {
        return Listing::findOrFail($this->listingId);
    }

    public function save(): void
    {
        $listing = $this->listingId ? $this->currentListing() : null;
        Gate::authorize($listing ? 'update' : 'create', $listing ?? Listing::class);
        $published = $listing?->publication_status === 'published';

        $data = $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(['rumah', 'tanah', 'kos'])],
            'priceIdr' => ['required', 'integer', 'min:1', 'max:999999999999999'],
            'province' => ['required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:255'],
            'district' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:2000'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'landAreaM2' => [$published ? 'required' : 'nullable', 'numeric', 'gt:0', 'max:99999999.99', 'decimal:0,2'],
            'buildingAreaM2' => [$published && $this->type !== 'tanah' ? 'required' : 'nullable', 'numeric', 'gt:0', 'max:99999999.99', 'decimal:0,2'],
            'rooms' => [$published && $this->type !== 'tanah' ? 'required' : 'nullable', 'integer', 'min:1', 'max:32767'],
            'bathrooms' => ['nullable', 'integer', 'min:0', 'max:32767'],
            'facilitiesText' => ['nullable', 'string', 'max:2000'],
            'description' => [$published ? 'required' : 'nullable', 'string', 'max:20000'],
        ]);

        $attributes = [
            'title' => trim($data['title']), 'type' => $data['type'], 'price_idr' => $data['priceIdr'],
            'province' => trim($data['province']), 'city' => trim($data['city']),
            'district' => $data['district'] ?: null, 'address' => $data['address'] ?: null,
            'latitude' => ($data['latitude'] ?? '') === '' ? null : $data['latitude'],
            'longitude' => ($data['longitude'] ?? '') === '' ? null : $data['longitude'],
            'land_area_m2' => $data['landAreaM2'] ?: null,
            'building_area_m2' => $data['type'] === 'tanah' ? null : ($data['buildingAreaM2'] ?: null),
            'rooms' => $data['type'] === 'tanah' || $data['rooms'] === '' ? null : $data['rooms'],
            'bathrooms' => $data['type'] === 'tanah' || $data['bathrooms'] === '' ? null : $data['bathrooms'],
            'facilities' => array_values(array_filter(array_map('trim', explode(',', $data['facilitiesText'])))),
            'description' => $data['description'] ?: null,
        ];
        if ($listing) {
            $listing->update($attributes);
        } else {
            $listing = DB::transaction(fn () => Listing::create($attributes));
            $this->listingId = $listing->id;
        }
        session()->flash('status', 'Properti disimpan.');
    }

    public function publish(): void
    {
        $listing = $this->currentListing();
        Gate::authorize('update', $listing);
        $result = DB::transaction(function () use ($listing): string {
            $locked = Listing::query()->lockForUpdate()->findOrFail($listing->id);
            if ($locked->publication_status === 'archived') {
                return 'archived';
            }
            if (! $locked->canPublish()) {
                return 'incomplete';
            }
            $locked->update(['publication_status' => 'published']);

            return 'published';
        });
        if ($result === 'archived') {
            $this->addError('publication', 'Kembalikan arsip ke draf sebelum menerbitkan.');

            return;
        }
        if ($result === 'incomplete') {
            $this->addError('publication', 'Lengkapi data utama dan unggah minimal satu foto sebelum menerbitkan.');
            return;
        }
    }

    public function archive(): void
    {
        $listing = $this->currentListing();
        Gate::authorize('update', $listing);
        $listing->update(['publication_status' => 'archived']);
    }

    public function restoreDraft(): void
    {
        $listing = $this->currentListing();
        Gate::authorize('update', $listing);
        $listing->update(['publication_status' => 'draft']);
    }

    public function setAvailability(string $status): void
    {
        $listing = $this->currentListing();
        Gate::authorize('update', $listing);
        if (! in_array($status, ['available', 'reserved', 'sold'], true)) {
            $this->addError('availability', 'Status ketersediaan tidak valid.');
            return;
        }
        $listing->update(['availability_status' => $status]);
    }

    public function savePhotos(): void
    {
        $listing = $this->currentListing();
        Gate::authorize('update', $listing);
        $this->validate([
            'newPhotos' => ['required', 'array', 'min:1'],
            'newPhotos.*' => ['required', 'file', 'mimetypes:image/jpeg,image/png,image/webp', 'max:10240'],
        ]);
        if ($listing->media()->where('kind', 'photo')->count() + count($this->newPhotos) > 20) {
            $this->addError('newPhotos', 'Maksimal 20 foto per properti.');
            return;
        }
        $service = app(ListingMediaService::class);
        foreach ($this->newPhotos as $photo) {
            $service->addPhoto($listing, $photo);
        }
        $this->reset('newPhotos');
        session()->flash('status', 'Foto ditambahkan.');
    }

    public function saveFloorplan(): void
    {
        $listing = $this->currentListing();
        Gate::authorize('update', $listing);
        $this->validate(['newFloorplan' => ['required', 'file', 'mimetypes:image/jpeg,image/png,image/webp,application/pdf', 'max:10240']]);
        app(ListingMediaService::class)->setFloorplan($listing, $this->newFloorplan);
        $this->reset('newFloorplan');
        session()->flash('status', 'Denah disimpan.');
    }

    private function currentMedia(int $mediaId): ListingMedia
    {
        return $this->currentListing()->media()->findOrFail($mediaId);
    }

    public function removeMedia(int $mediaId): void
    {
        $listing = $this->currentListing();
        Gate::authorize('update', $listing);
        app(ListingMediaService::class)->remove($this->currentMedia($mediaId));
    }

    public function makePrimary(int $mediaId): void
    {
        $listing = $this->currentListing();
        Gate::authorize('update', $listing);
        app(ListingMediaService::class)->makePrimary($this->currentMedia($mediaId));
    }

    public function movePhoto(int $mediaId, int $direction): void
    {
        $listing = $this->currentListing();
        Gate::authorize('update', $listing);
        app(ListingMediaService::class)->movePhoto($this->currentMedia($mediaId), $direction);
    }
}; ?>

<div class="mx-auto max-w-5xl space-y-6 p-6">
    <flux:button :href="route('listings.index')" variant="ghost">← Inventaris</flux:button>
    <flux:heading size="xl">{{ $listingId ? 'Ubah properti' : 'Tambah properti' }}</flux:heading>
    @if (session('status'))<flux:callout variant="success">{{ session('status') }}</flux:callout>@endif
    <form wire:submit="save" class="grid gap-4 rounded-xl border border-zinc-200 p-6 md:grid-cols-2 dark:border-zinc-700">
        <flux:input wire:model="title" label="Judul" required />
        <flux:select wire:model="type" label="Tipe"><flux:select.option value="rumah">Rumah</flux:select.option><flux:select.option value="tanah">Tanah</flux:select.option><flux:select.option value="kos">Kos</flux:select.option></flux:select>
        <flux:input wire:model="priceIdr" label="Harga jual (Rp)" type="number" min="1" required />
        <flux:input wire:model="province" label="Provinsi" required />
        <flux:input wire:model="city" label="Kota/kabupaten" required />
        <flux:input wire:model="district" label="Kecamatan" />
        <flux:input wire:model="address" label="Alamat" />
        <flux:input wire:model="landAreaM2" label="Luas tanah (m²)" type="number" step="0.01" />
        <flux:input wire:model="buildingAreaM2" label="Luas bangunan (m²)" type="number" step="0.01" />
        <flux:input wire:model="rooms" label="Jumlah kamar" type="number" />
        <flux:input wire:model="bathrooms" label="Jumlah kamar mandi" type="number" />
        <flux:input wire:model="latitude" label="Latitude" type="number" step="any" />
        <flux:input wire:model="longitude" label="Longitude" type="number" step="any" />
        <flux:input wire:model="facilitiesText" label="Fasilitas (pisahkan koma)" />
        <div class="md:col-span-2"><flux:textarea wire:model="description" label="Deskripsi" rows="6" /></div>
        <div class="md:col-span-2"><flux:button type="submit" variant="primary">Simpan draf/perubahan</flux:button></div>
    </form>
    @if ($listingId)
        @php($current = \App\Models\Listing::find($listingId))
        <section class="space-y-5 rounded-xl border border-zinc-200 p-6 dark:border-zinc-700">
            <div><flux:heading size="lg">Foto dan denah</flux:heading><flux:text>Maksimal 20 foto dan satu denah; masing-masing hingga 10 MB.</flux:text></div>
            <form wire:submit="savePhotos" class="space-y-3">
                <label for="listing-photos" class="block text-sm font-medium">Tambah foto (JPEG, PNG, WebP)</label>
                <input id="listing-photos" type="file" wire:model="newPhotos" accept="image/jpeg,image/png,image/webp" multiple class="block w-full text-sm">
                @error('newPhotos')<p class="text-red-600">{{ $message }}</p>@enderror
                @error('newPhotos.*')<p class="text-red-600">{{ $message }}</p>@enderror
                <flux:button type="submit" wire:loading.attr="disabled" wire:target="newPhotos">Unggah foto</flux:button>
            </form>
            <div class="grid gap-4 sm:grid-cols-2">
                @foreach ($current->media()->where('kind', 'photo')->get() as $photo)
                    <div wire:key="photo-{{ $photo->id }}" class="space-y-2 rounded-lg border p-3 dark:border-zinc-700">
                        <img src="{{ $photo->url }}" alt="Foto {{ $current->title }}" class="h-44 w-full rounded object-cover">
                        <div class="flex flex-wrap gap-2">
                            @if ($photo->position === 0)<span class="text-sm font-medium">Foto utama</span>@else<flux:button size="sm" wire:click="makePrimary({{ $photo->id }})">Jadikan utama</flux:button>@endif
                            <flux:button size="sm" wire:click="movePhoto({{ $photo->id }}, -1)">↑</flux:button>
                            <flux:button size="sm" wire:click="movePhoto({{ $photo->id }}, 1)">↓</flux:button>
                            <flux:button size="sm" variant="danger" wire:click="removeMedia({{ $photo->id }})">Hapus</flux:button>
                        </div>
                    </div>
                @endforeach
            </div>
            @error('photos')<p class="text-red-600">{{ $message }}</p>@enderror
            <form wire:submit="saveFloorplan" class="space-y-3">
                <label for="listing-floorplan" class="block text-sm font-medium">Denah (gambar atau PDF)</label>
                <input id="listing-floorplan" type="file" wire:model="newFloorplan" accept="image/jpeg,image/png,image/webp,application/pdf" class="block w-full text-sm">
                @error('newFloorplan')<p class="text-red-600">{{ $message }}</p>@enderror
                <flux:button type="submit" wire:loading.attr="disabled" wire:target="newFloorplan">Simpan denah</flux:button>
            </form>
            @foreach ($current->media()->where('kind', 'floorplan')->get() as $floorplan)
                <div wire:key="floorplan-{{ $floorplan->id }}" class="flex gap-3"><a href="{{ $floorplan->url }}" target="_blank" rel="noopener" class="underline">Lihat denah</a><flux:button size="sm" variant="danger" wire:click="removeMedia({{ $floorplan->id }})">Hapus</flux:button></div>
            @endforeach
        </section>
        <section class="space-y-3 rounded-xl border border-zinc-200 p-6 dark:border-zinc-700">
            <flux:heading size="lg">Status</flux:heading>
            <p>{{ ucfirst($current->publication_status) }} · {{ ucfirst($current->availability_status) }}</p>
            @error('publication')<p class="text-red-600">{{ $message }}</p>@enderror
            @error('availability')<p class="text-red-600">{{ $message }}</p>@enderror
            <div class="flex flex-wrap gap-2">
                <flux:button wire:click="publish">Terbitkan</flux:button>
                <flux:button wire:click="restoreDraft">Jadikan draf</flux:button>
                <flux:button wire:click="archive" variant="danger">Arsipkan</flux:button>
                <flux:button wire:click="setAvailability('available')">Tersedia</flux:button>
                <flux:button wire:click="setAvailability('reserved')">Dipesan</flux:button>
                <flux:button wire:click="setAvailability('sold')">Terjual</flux:button>
            </div>
        </section>
    @endif
</div>
