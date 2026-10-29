<?php

namespace App\Services;

use App\Ai\Agents\PropertyAssistantAgent;
use App\Ai\Tools\CompareListings;
use App\Ai\Tools\SalesContacts;
use App\Ai\Tools\SearchListings;
use App\Models\Listing;
use App\Models\User;
use App\Queries\ListingSearch;
use App\Support\AssistantGuard;

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
        $message = AssistantGuard::redact($message);
        $search = new SearchListings($this->search);
        $compare = new CompareListings($lastIds);
        $contacts = new SalesContacts($search, $compare, $lastIds);

        $response = (new PropertyAssistantAgent([$search, $compare, $contacts]))->prompt(json_encode([
            'pesan' => $message,
            'riwayat_pesan_pengguna' => array_map(AssistantGuard::redact(...), $history),
            'jumlah_hasil_sebelumnya' => count($lastIds),
        ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), provider: 'gemini', model: config('assistant.model'), timeout: 15);
        $scope = $response['scope'] ?? 'properti';

        if ($scope === 'di_luar_topik') {
            return $this->payload('Maaf, saya hanya dapat membantu pencarian dan informasi properti di Aditamaland.');
        }

        $isComparison = ! $search->listingIds && $compare->listingIds !== null;
        // A listing may change while Gemini is responding. Use the latest public rows for output.
        $ids = $search->listingIds ?: $compare->listingIds ?: [];
        $latest = Listing::publicCatalog()->whereIn('id', $ids)->get()->keyBy('id');
        $facts = collect($ids)->map(fn (int $id) => $latest->get($id))->filter()
            ->map(fn (Listing $listing) => $listing->assistantFacts() + ['url' => route('properties.show', $listing)])->values()->all();

        // A contact request already answered by SalesContacts is never "data not recorded".
        if ($scope === 'data_tidak_tersedia' && $contacts->sales === null) {
            $answer = $contacts->lookup()->isEmpty()
                ? 'Informasi itu belum tercatat di data kami, dan kontak sales belum tersedia saat ini.'
                : 'Informasi itu belum tercatat di data kami. Tanyakan langsung kepada sales.';
        } elseif ($contacts->sales?->isEmpty() && ! $facts) {
            $answer = $contacts->listingUnavailable
                ? 'Properti itu tidak lagi tersedia, jadi kontak sales tidak ditampilkan. Cari properti lain di katalog.'
                : 'Maaf, kontak sales belum tersedia saat ini. Silakan cek kembali nanti atau gunakan filter katalog.';
        } else {
            $answer = (string) ($response['answer'] ?? '');
            if (! AssistantGuard::isGrounded($answer, $facts, $message)) {
                $answer = $this->fallback($search, $compare, $contacts, $lastIds, count($facts));
            }
        }

        return $this->payload($answer, $isComparison ? 'compare' : 'search', $facts, $contacts);
    }

    /** @param  list<int>  $lastIds */
    private function fallback(SearchListings $search, CompareListings $compare, SalesContacts $contacts, array $lastIds, int $found): string
    {
        return match (true) {
            $search->listingIds !== null => $found ? "Saya menemukan {$found} properti tersedia." : 'Tidak ada properti yang cocok saat ini. Coba longgarkan kriteria di katalog.',
            $compare->listingIds !== null => match (true) {
                ! $lastIds => 'Belum ada hasil untuk dibandingkan. Cari properti terlebih dahulu.',
                $found === 0 => 'Pilihan sebelumnya tidak lagi tersedia. Cari ulang di katalog.',
                $found === 1 => 'Hanya satu pilihan sebelumnya yang masih tersedia. Pilih properti lain untuk perbandingan.',
                default => "Berikut perbandingan {$found} properti yang masih tersedia.",
            },
            $contacts->sales !== null => 'Berikut kontak sales yang dapat Anda hubungi.',
            default => 'Maaf, saya belum bisa menjawab itu. Ceritakan properti yang Anda cari atau gunakan filter katalog.',
        };
    }

    /**
     * @param  list<array<string, mixed>>  $listings
     * @return array<string, mixed>
     */
    private function payload(string $answer, string $mode = 'search', array $listings = [], ?SalesContacts $contacts = null): array
    {
        $listing = $contacts?->listing;

        return [
            'answer' => $answer,
            'mode' => $mode,
            'listings' => $listings,
            'contacts' => ($contacts?->sales ?? collect())->map(fn (User $sales) => [
                'name' => $sales->name, 'whatsapp_url' => $sales->whatsappUrl($listing), 'email' => $sales->contact_email,
            ])->all(),
            'catalog_url' => route('properties.index'),
            'contacts_url' => $listing ? route('properties.show', $listing).'#kontak' : route('home').'#tim-sales',
        ];
    }
}
