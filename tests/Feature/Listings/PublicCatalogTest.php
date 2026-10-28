<?php

namespace Tests\Feature\Listings;

use App\Models\Listing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PublicCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_and_catalog_only_show_published_available_listings(): void
    {
        Storage::fake('public');
        $available = Listing::factory()->create(['title' => 'Rumah Contoh Publik', 'publication_status' => 'published']);
        $available->media()->create(['kind' => 'photo', 'disk' => 'public', 'object_key' => 'listings/front.jpg', 'mime_type' => 'image/jpeg', 'position' => 0]);
        Listing::factory()->create(['title' => 'Rumah Sudah Dipesan', 'publication_status' => 'published', 'availability_status' => 'reserved']);
        Listing::factory()->create(['title' => 'Rumah Masih Draf', 'publication_status' => 'draft']);
        Listing::factory()->create(['title' => 'Rumah Diarsipkan', 'publication_status' => 'archived']);

        $this->get('/')->assertOk()->assertSee('Rumah Contoh Publik')->assertDontSee('Rumah Sudah Dipesan');
        $this->get('/properti')->assertOk()
            ->assertSee('Rumah Contoh Publik')->assertSee('Rp 750.000.000')
            ->assertSee('Bandung')->assertSee('100 m²')
            ->assertSee(route('properties.show', $available))
            ->assertDontSee('Rumah Masih Draf')->assertDontSee('Rumah Diarsipkan')
            ->assertDontSee('Rumah Sudah Dipesan');
    }

    public function test_published_reserved_and_sold_details_remain_public_but_draft_and_archive_are_hidden(): void
    {
        $reserved = Listing::factory()->create(['publication_status' => 'published', 'availability_status' => 'reserved']);
        $sold = Listing::factory()->create(['publication_status' => 'published', 'availability_status' => 'sold']);
        $draft = Listing::factory()->create();
        $archived = Listing::factory()->create(['publication_status' => 'archived']);

        $this->get('/properti/'.$reserved->slug)->assertOk()->assertSee('Dipesan');
        $this->get('/properti/'.$sold->slug)->assertOk()->assertSee('Terjual');
        $this->get('/properti/'.$draft->slug)->assertNotFound();
        $this->get('/properti/'.$archived->slug)->assertNotFound();
    }

    public function test_availability_and_publication_changes_immediately_update_public_pages(): void
    {
        $listing = Listing::factory()->create([
            'title' => 'Rumah Berubah Status', 'publication_status' => 'published',
        ]);

        $this->get('/properti')->assertSee('Rumah Berubah Status');
        $listing->update(['availability_status' => 'reserved']);
        $this->get('/properti')->assertDontSee('Rumah Berubah Status');
        $this->get('/properti/'.$listing->slug)->assertOk()->assertSee('Dipesan');

        $listing->update(['availability_status' => 'sold']);
        $this->get('/properti/'.$listing->slug)->assertOk()->assertSee('Terjual');

        $listing->update(['publication_status' => 'archived']);
        $this->get('/properti/'.$listing->slug)->assertNotFound();
    }

    public function test_detail_omits_unknown_optional_values_and_has_metadata(): void
    {
        $listing = Listing::factory()->create([
            'publication_status' => 'published', 'latitude' => null, 'longitude' => null,
            'address' => null, 'building_area_m2' => null, 'bathrooms' => null,
        ]);

        $this->get('/properti/'.$listing->slug)->assertOk()
            ->assertSee($listing->code)->assertSee($listing->description)
            ->assertDontSee('Luas bangunan')->assertDontSee('Kamar mandi')->assertDontSee('Buka peta')
            ->assertSee('<meta name="description"', false);
    }

    public function test_installment_calculator_uses_current_price_on_all_published_details(): void
    {
        foreach (['available', 'reserved', 'sold'] as $status) {
            $listing = Listing::factory()->create([
                'publication_status' => 'published', 'availability_status' => $status, 'price_idr' => 900000000,
            ]);

            $this->get(route('properties.show', $listing))->assertOk()
                ->assertSee('data-mortgage-calculator', false)
                ->assertSee('data-mortgage-price value="900000000"', false)
                ->assertSee('bukan penawaran atau persetujuan bank');

            $listing->update(['price_idr' => 950000000]);
            $this->get(route('properties.show', $listing))->assertOk()
                ->assertSee('data-mortgage-price value="950000000"', false);
        }

        $draft = Listing::factory()->create(['publication_status' => 'draft']);
        $archived = Listing::factory()->create(['publication_status' => 'archived']);
        $this->get(route('properties.show', $draft))->assertNotFound();
        $this->get(route('properties.show', $archived))->assertNotFound();
    }

    public function test_catalog_shows_clear_empty_state_and_paginates(): void
    {
        $this->get('/properti')->assertOk()->assertSee('Belum ada properti tersedia');

        Listing::factory()->count(13)->create(['publication_status' => 'published']);
        $this->get('/properti')->assertOk()->assertSee('page=2');
    }
}
