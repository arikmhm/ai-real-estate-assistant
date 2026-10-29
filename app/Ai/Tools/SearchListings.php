<?php

namespace App\Ai\Tools;

use App\Models\Listing;
use App\Queries\ListingSearch;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class SearchListings implements Tool
{
    private const TEXT_FILTERS = ['q', 'city', 'district'];

    private const NUMBER_FILTERS = ['price_min', 'price_max', 'land_min', 'land_max', 'building_min', 'building_max', 'rooms_min'];

    /** Null until the model calls this tool. @var list<int>|null */
    public ?array $listingIds = null;

    public function __construct(private readonly ListingSearch $search) {}

    public function description(): Stringable|string
    {
        $locations = Listing::publicCatalog()->select('city', 'district')->distinct()->limit(60)->get()
            ->map(fn (Listing $listing) => trim($listing->district.' | '.$listing->city, ' |'))->implode('; ');

        return 'Cari maksimal 3 properti yang diterbitkan dan tersedia. Harga dalam rupiah penuh (700 juta = 700000000). '
            .'Isi city/district persis dengan nama dari daftar lokasi ini (kecamatan | kota): '.$locations;
    }

    public function schema(JsonSchema $schema): array
    {
        $fields = ['type' => $schema->string()->enum(['rumah', 'tanah', 'kos'])->nullable()];
        foreach (self::TEXT_FILTERS as $field) {
            $fields[$field] = $schema->string()->nullable();
        }
        foreach (self::NUMBER_FILTERS as $field) {
            $fields[$field] = $schema->integer()->nullable();
        }

        return $fields;
    }

    public function handle(Request $request): Stringable|string
    {
        $rules = ['type' => ['nullable', 'in:rumah,tanah,kos']];
        foreach (self::TEXT_FILTERS as $field) {
            $rules[$field] = ['nullable', 'string', 'max:100'];
        }
        foreach (self::NUMBER_FILTERS as $field) {
            $rules[$field] = ['nullable', 'integer', 'min:0'];
        }
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return 'Argumen tidak valid: '.$validator->errors()->first();
        }

        $listings = $this->search->query($validator->validated())->orderByDesc('updated_at')->limit(3)->get();
        $this->listingIds = $listings->pluck('id')->all();

        return self::describe($listings);
    }

    /** @param  iterable<Listing>  $listings */
    public static function describe(iterable $listings): string
    {
        $rows = [];
        foreach ($listings as $index => $listing) {
            $facts = $listing->assistantFacts();
            // Free-text description is never sent; short fields are capped to limit injected text.
            $rows[] = ['posisi' => $index + 1, 'kode' => $facts['code'], 'judul' => Str::limit($facts['title'], 100),
                'tipe' => $facts['type'], 'harga' => $facts['price_formatted'],
                'kota' => Str::limit($facts['city'] ?? '', 80), 'kecamatan' => Str::limit($facts['district'] ?? '', 80),
                'luas_tanah_m2' => $facts['land_area_m2'], 'luas_bangunan_m2' => $facts['building_area_m2'],
                'kamar' => $facts['rooms'],
                'fasilitas' => array_map(fn ($facility) => Str::limit((string) $facility, 60), $facts['facilities'])];
        }

        return json_encode(['jumlah' => count($rows), 'hasil' => $rows], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
}
