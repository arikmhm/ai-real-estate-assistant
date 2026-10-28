<?php

namespace App\Services;

use App\Ai\Agents\ListingExplanationAgent;
use App\Ai\Agents\SearchIntentAgent;
use App\Models\Listing;
use App\Queries\ListingSearch;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use RuntimeException;

class AssistantService
{
    public function __construct(private readonly ListingSearch $search) {}

    /**
     * @param  list<string>  $history
     * @param  list<int>  $lastIds
     * @return array<string, mixed>
     */
    public function reply(string $message, array $history, array $lastIds): array
    {
        $locations = Listing::publicCatalog()->select('city', 'district')->distinct()->limit(60)->get()->toArray();
        $intent = SearchIntentAgent::make()->prompt(json_encode([
            'pesan' => $message, 'riwayat_pesan_pengguna' => $history,
            'lokasi_tersedia' => $locations,
            'jumlah_hasil_sebelumnya' => count($lastIds),
        ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), provider: 'gemini', model: config('assistant.model'), timeout: 15)->toArray();

        $rules = ['intent' => ['required', 'in:search,compare'], 'compare_positions' => ['present', 'array', 'max:3'], 'compare_positions.*' => ['integer', 'between:1,3', 'distinct']];
        foreach (['q', 'city', 'district', 'type'] as $field) {
            $rules[$field] = ['nullable', 'string', 'max:100'];
        }
        foreach (['price_min', 'price_max', 'land_min', 'land_max', 'building_min', 'building_max', 'rooms_min'] as $field) {
            $rules[$field] = ['nullable', 'integer', 'min:0'];
        }
        if (Validator::make($intent, $rules)->fails()) {
            throw new RuntimeException('Invalid intent response');
        }

        $isComparison = $intent['intent'] === 'compare';
        if ($isComparison) {
            $positions = $intent['compare_positions'] ?: range(1, count($lastIds));
            $ids = array_values(array_filter(array_map(fn (int $position) => $lastIds[$position - 1] ?? null, $positions)));
            $current = Listing::publicCatalog()->whereIn('id', $ids)->get()->keyBy('id');
            $listings = collect($ids)->map(fn (int $id) => $current->get($id))->filter()->values();
        } else {
            $filters = array_intersect_key($intent, array_flip(['q', 'city', 'district', 'type', 'price_min', 'price_max', 'land_min', 'land_max', 'building_min', 'building_max', 'rooms_min']));
            if (! in_array($filters['type'] ?? null, ['rumah', 'tanah', 'kos'], true)) {
                unset($filters['type']);
            }
            $listings = $this->search->query($filters)->orderByDesc('updated_at')->limit(3)->get();
        }

        if ($isComparison && $listings->isEmpty()) {
            return ['answer' => 'Pilihan sebelumnya tidak lagi tersedia. Cari ulang di katalog.', 'listings' => [], 'catalog_url' => route('properties.index')];
        }

        $facts = $listings->map(fn (Listing $listing) => $this->facts($listing))->all();
        $promptFacts = array_map(fn (array $row) => [
            'id' => $row['id'], 'title' => Str::limit($row['title'], 100), 'type' => $row['type'],
            'price_idr' => $row['price_idr'], 'city' => Str::limit($row['city'] ?? '', 80),
            'district' => Str::limit($row['district'] ?? '', 80), 'land_area_m2' => $row['land_area_m2'],
            'building_area_m2' => $row['building_area_m2'], 'rooms' => $row['rooms'],
            'facilities' => array_map(fn ($facility) => Str::limit((string) $facility, 60), $row['facilities']),
        ], $facts);
        $explanation = ListingExplanationAgent::make()->prompt(json_encode([
            'pesan' => $message, 'mode' => $isComparison ? 'compare' : 'search', 'hasil' => $promptFacts,
        ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), provider: 'gemini', model: config('assistant.model'), timeout: 15)->toArray();

        // A listing may change while Gemini is responding. Use the latest public rows for output.
        $resultIds = $listings->pluck('id')->all();
        if ($resultIds) {
            $latest = Listing::publicCatalog()->whereIn('id', $resultIds)->get()->keyBy('id');
            $listings = collect($resultIds)->map(fn (int $id) => $latest->get($id))->filter()->values();
            $facts = $listings->map(fn (Listing $listing) => $this->facts($listing))->all();
        }

        $keys = array_values(array_intersect((array) ($explanation['reason_keys'] ?? []), ['location', 'price', 'type', 'land', 'building', 'rooms', 'facilities']));
        $answer = $listings->isEmpty()
            ? ($isComparison ? 'Pilihan sebelumnya tidak lagi tersedia. Cari ulang di katalog.' : 'Tidak ada properti yang cocok saat ini. Coba longgarkan kriteria di katalog.')
            : ($isComparison
                ? (count($facts) < 2 ? 'Hanya satu pilihan sebelumnya yang masih tersedia. Pilih properti lain untuk perbandingan.' : 'Berikut perbandingan '.count($facts).' properti yang masih tersedia.')
                : 'Saya menemukan '.count($facts).' properti tersedia.');
        if (! $isComparison && $listings->isNotEmpty() && ! array_filter($filters, fn ($value) => $value !== null && $value !== '')) {
            $answer .= ' Ini adalah hasil yang paling baru diperbarui sebagai titik awal. Lokasi atau anggaran apa yang Anda inginkan?';
        }
        if ($listings->isEmpty()) {
            $answer .= match ($explanation['question'] ?? 'none') {
                'budget' => ' Berapa anggaran Anda?', 'location' => ' Lokasi mana yang Anda cari?', 'type' => ' Tipe properti apa yang Anda cari?', default => '',
            };
        }

        return [
            'answer' => $answer,
            'mode' => $isComparison ? 'compare' : 'search',
            'listings' => $listings->map(function (Listing $listing) use ($keys): array {
                $facts = $this->facts($listing);
                $facts['reason'] = $this->reason($facts, $keys);
                $facts['url'] = route('properties.show', $listing);

                return $facts;
            })->all(),
            'catalog_url' => route('properties.index'),
        ];
    }

    /** @return array<string, mixed> */
    private function facts(Listing $listing): array
    {
        return [
            'id' => $listing->id, 'code' => $listing->code, 'title' => $listing->title,
            'type' => $listing->type, 'price_idr' => $listing->price_idr,
            'price_formatted' => 'Rp'.number_format($listing->price_idr, 0, ',', '.'),
            'city' => $listing->city, 'district' => $listing->district,
            'land_area_m2' => $listing->land_area_m2, 'building_area_m2' => $listing->building_area_m2,
            'rooms' => $listing->rooms,
            'facilities' => array_slice((array) $listing->facilities, 0, 5),
        ];
    }

    /**
     * @param  array<string, mixed>  $facts
     * @param  list<string>  $keys
     */
    private function reason(array $facts, array $keys): string
    {
        $parts = [];
        foreach (array_slice($keys, 0, 3) as $key) {
            $value = match ($key) {
                'location' => trim(($facts['district'] ?: '').', '.($facts['city'] ?: ''), ', '),
                'price' => $facts['price_formatted'], 'type' => ucfirst($facts['type']),
                'land' => $facts['land_area_m2'] ? $facts['land_area_m2'].' m² tanah' : null,
                'building' => $facts['building_area_m2'] ? $facts['building_area_m2'].' m² bangunan' : null,
                'rooms' => $facts['rooms'] ? $facts['rooms'].' kamar' : null,
                'facilities' => implode(', ', $facts['facilities']), default => null,
            };
            if ($value) {
                $parts[] = $value;
            }
        }

        return $parts ? implode(' · ', $parts) : 'Lihat detail properti untuk informasi lengkap.';
    }
}
