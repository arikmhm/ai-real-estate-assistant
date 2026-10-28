<?php

namespace Tests\Feature\Listings;

use App\Models\Listing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_combined_filters_only_return_matching_public_listing(): void
    {
        $match = Listing::factory()->create([
            'title' => 'Rumah Keluarga Kudus', 'description' => 'Dekat taman', 'facilities' => ['Carport'],
            'publication_status' => 'published', 'city' => 'Kabupaten Kudus', 'district' => 'Bae',
            'price_idr' => 600000000, 'land_area_m2' => 120, 'building_area_m2' => 90, 'rooms' => 3,
        ]);
        Listing::factory()->create(['title' => 'Rumah Mahal', 'publication_status' => 'published', 'city' => 'Kabupaten Kudus', 'district' => 'Bae', 'price_idr' => 900000000]);
        Listing::factory()->create(['title' => 'Rumah Draf Kudus', 'publication_status' => 'draft', 'city' => 'Kabupaten Kudus', 'district' => 'Bae']);

        $this->get('/properti?'.http_build_query([
            'q' => 'CARPORT', 'city' => 'Kabupaten Kudus', 'district' => 'Bae', 'type' => 'rumah',
            'price_min' => 500000000, 'price_max' => 700000000,
            'land_min' => 100, 'land_max' => 140, 'building_min' => 80, 'building_max' => 100, 'rooms_min' => 3,
        ]))->assertOk()->assertSee($match->title)->assertDontSee('Rumah Mahal')->assertDontSee('Rumah Draf Kudus');
    }

    public function test_area_and_room_filters_exclude_unknown_values(): void
    {
        Listing::factory()->create(['title' => 'Tanah Tanpa Bangunan', 'type' => 'tanah', 'publication_status' => 'published', 'building_area_m2' => null, 'rooms' => null]);
        $this->get('/properti?building_min=50')->assertDontSee('Tanah Tanpa Bangunan');
        $this->get('/properti?rooms_min=1')->assertDontSee('Tanah Tanpa Bangunan');
    }

    public function test_invalid_filter_shows_errors_without_running_unfiltered_search(): void
    {
        Listing::factory()->create(['title' => 'Rumah Harus Tersembunyi', 'publication_status' => 'published']);

        $this->get('/properti?price_min=-1&price_max=0&type=villa&district=Bae')
            ->assertOk()->assertSee('Perbaiki filter')->assertDontSee('Rumah Harus Tersembunyi');
        $this->get('/properti?price_min=900&price_max=500')
            ->assertOk()->assertSee('Perbaiki filter')->assertDontSee('Rumah Harus Tersembunyi');
        $this->get('/properti?land_min=120&land_max=100&building_min=90&building_max=80&rooms_min=-1')
            ->assertOk()->assertSee('Perbaiki filter')->assertDontSee('Rumah Harus Tersembunyi');
        $this->get('/properti?q[]=rumah')
            ->assertOk()->assertSee('Perbaiki filter')->assertDontSee('Rumah Harus Tersembunyi');
    }

    public function test_district_must_belong_to_selected_city(): void
    {
        Listing::factory()->create(['title' => 'Rumah Bae', 'publication_status' => 'published', 'city' => 'Kabupaten Kudus', 'district' => 'Bae']);
        Listing::factory()->create(['title' => 'Rumah Jepara', 'publication_status' => 'published', 'city' => 'Kabupaten Jepara', 'district' => 'Tahunan']);

        $this->get('/properti?city=Kabupaten+Jepara&district=Bae')
            ->assertOk()->assertSee('Kecamatan tidak tersedia')->assertDontSee('Rumah Bae')->assertDontSee('Rumah Jepara');
    }

    public function test_filtered_empty_state_reset_and_pagination_preserve_query(): void
    {
        Listing::factory()->count(13)->create(['publication_status' => 'published', 'city' => 'Kabupaten Kudus']);

        $this->get('/properti?city=Kabupaten+Kudus')->assertOk()->assertSee('page=2')->assertSee('city=Kabupaten');
        $this->get('/properti?q=tidak-ada')->assertOk()
            ->assertSee('Tidak ada properti yang cocok')->assertSee(route('properties.index'));
    }

    public function test_catalog_empty_state_is_distinct_from_filtered_empty_state(): void
    {
        $this->get('/properti')->assertOk()->assertSee('Belum ada properti tersedia');
        $this->get('/properti?q=rumah')->assertOk()->assertSee('Tidak ada properti yang cocok');
    }
}
