<?php

namespace App\Ai\Tools;

use App\Models\Listing;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class SalesContacts implements Tool
{
    /** Null until contacts are looked up. @var Collection<int, User>|null */
    public ?Collection $sales = null;

    public ?Listing $listing = null;

    public bool $listingUnavailable = false;

    /** @param  list<int>  $lastIds */
    public function __construct(
        private readonly SearchListings $search,
        private readonly CompareListings $compare,
        private readonly array $lastIds,
    ) {}

    public function description(): Stringable|string
    {
        return 'Tampilkan kartu kontak sales (maksimal 3). Isi position 1 sampai 3 bila pengguna merujuk properti dari hasil terakhir. Hasil hanya berisi nama sales.';
    }

    public function schema(JsonSchema $schema): array
    {
        return ['position' => $schema->integer()->nullable()];
    }

    public function handle(Request $request): Stringable|string
    {
        $validator = Validator::make($request->all(), ['position' => ['nullable', 'integer', 'between:1,3']]);
        if ($validator->fails()) {
            return 'Argumen tidak valid: '.$validator->errors()->first();
        }

        $position = $validator->validated()['position'] ?? null;
        $listing = null;
        if ($position) {
            $id = ($this->search->listingIds ?: $this->compare->listingIds ?: $this->lastIds)[$position - 1] ?? null;
            $listing = $id ? Listing::publicDetail()->find($id) : null;
            // FR-04: sold or unpublished listings never get sales contacts.
            if (! $listing || $listing->isSold()) {
                $this->sales = collect();
                $this->listingUnavailable = true;

                return 'Properti itu tidak lagi tersedia, jadi kontak sales tidak ditampilkan.';
            }
        }

        $sales = $this->lookup($listing);

        return json_encode(['jumlah' => $sales->count(), 'nama_sales' => $sales->pluck('name')->all()], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    /** @return Collection<int, User> */
    public function lookup(?Listing $listing = null): Collection
    {
        $this->listing = $listing;

        return $this->sales = User::activeSales()->where('public_contact_approved', true)->orderBy('name')->get()
            ->filter(fn (User $user) => $user->whatsappUrl() !== null)->take(3)->values();
    }
}
