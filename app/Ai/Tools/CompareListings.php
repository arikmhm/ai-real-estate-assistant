<?php

namespace App\Ai\Tools;

use App\Models\Listing;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Validator;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class CompareListings implements Tool
{
    /** Null until the model calls this tool. @var list<int>|null */
    public ?array $listingIds = null;

    /** @param  list<int>  $lastIds */
    public function __construct(private readonly array $lastIds) {}

    public function description(): Stringable|string
    {
        return 'Bandingkan hasil pencarian sebelumnya (maksimal 3) berdasarkan posisi 1 sampai 3. Kosongkan positions untuk semua. Jumlah hasil sebelumnya: '.count($this->lastIds).'.';
    }

    public function schema(JsonSchema $schema): array
    {
        return ['positions' => $schema->array()->items($schema->integer())->nullable()];
    }

    public function handle(Request $request): Stringable|string
    {
        $validator = Validator::make($request->all(), [
            'positions' => ['nullable', 'array', 'max:3'], 'positions.*' => ['integer', 'between:1,3', 'distinct'],
        ]);
        if ($validator->fails()) {
            return 'Argumen tidak valid: '.$validator->errors()->first();
        }
        if (! $this->lastIds) {
            $this->listingIds = [];

            return 'Belum ada hasil sebelumnya untuk dibandingkan.';
        }

        $positions = ($validator->validated()['positions'] ?? []) ?: range(1, count($this->lastIds));
        $ids = array_values(array_filter(array_map(fn (int $position) => $this->lastIds[$position - 1] ?? null, $positions)));
        $current = Listing::publicCatalog()->whereIn('id', $ids)->get()->keyBy('id');
        $listings = collect($ids)->map(fn (int $id) => $current->get($id))->filter()->values();
        $this->listingIds = $listings->pluck('id')->all();

        return $listings->isEmpty() ? 'Pilihan sebelumnya tidak lagi tersedia.' : SearchListings::describe($listings);
    }
}
