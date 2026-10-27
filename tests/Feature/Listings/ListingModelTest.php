<?php

namespace Tests\Feature\Listings;

use App\Models\Listing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ListingModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_code_and_slug_stay_stable_after_title_change(): void
    {
        $listing = Listing::factory()->create(['title' => 'Rumah Taman Satu']);
        $code = $listing->code;
        $slug = $listing->slug;

        $listing->update(['title' => 'Rumah Taman Dua']);

        $this->assertSame('PR-'.str_pad((string) $listing->id, 6, '0', STR_PAD_LEFT), $code);
        $this->assertSame($slug, $listing->fresh()->slug);
    }

    public function test_long_title_still_creates_a_routeable_slug(): void
    {
        $listing = Listing::factory()->create(['title' => str_repeat('a', 255)]);

        $this->assertNotNull($listing->fresh()->slug);
        $this->assertLessThanOrEqual(255, strlen($listing->slug));
        $this->get('/properti/'.$listing->slug)->assertNotFound();
    }

    public function test_catalog_only_contains_published_available_and_detail_all_published(): void
    {
        $available = Listing::factory()->create(['publication_status' => 'published', 'availability_status' => 'available']);
        $reserved = Listing::factory()->create(['publication_status' => 'published', 'availability_status' => 'reserved']);
        Listing::factory()->create(['publication_status' => 'draft', 'availability_status' => 'available']);
        Listing::factory()->create(['publication_status' => 'archived', 'availability_status' => 'available']);

        $this->assertEqualsCanonicalizing([$available->id], Listing::publicCatalog()->pluck('id')->all());
        $this->assertEqualsCanonicalizing([$available->id, $reserved->id], Listing::publicDetail()->pluck('id')->all());
    }

    public function test_publication_requires_complete_details_and_photo(): void
    {
        $listing = Listing::factory()->create(['type' => 'rumah']);
        $this->assertFalse($listing->canPublish());

        $listing->media()->create([
            'kind' => 'photo', 'disk' => 'public', 'object_key' => 'listings/example.jpg',
            'mime_type' => 'image/jpeg', 'position' => 0,
        ]);

        $this->assertTrue($listing->fresh()->canPublish());

        $land = Listing::factory()->create(['type' => 'tanah', 'building_area_m2' => null, 'rooms' => null]);
        $land->media()->create([
            'kind' => 'photo', 'disk' => 'public', 'object_key' => 'listings/land.jpg',
            'mime_type' => 'image/jpeg', 'position' => 0,
        ]);
        $this->assertTrue($land->fresh()->canPublish());
    }
}
