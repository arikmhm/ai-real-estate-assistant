<?php

namespace Tests\Feature\Listings;

use App\Models\Listing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Exceptions\PublicPropertyNotFoundException;
use Livewire\Livewire;
use Tests\TestCase;

class ManageListingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_sales_and_admin_have_expected_page_access(): void
    {
        $listing = Listing::factory()->create();
        $this->get('/listings')->assertRedirect(route('login'));
        $this->get('/listings/create')->assertRedirect(route('login'));

        $this->actingAs(User::factory()->create());
        $this->get('/listings')->assertOk();
        $this->get('/listings/'.$listing->slug)->assertOk();
        $this->get('/listings/create')->assertForbidden();
        $this->get('/listings/'.$listing->slug.'/edit')->assertForbidden();

        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->get('/listings/create')->assertOk();
        $this->get('/listings/'.$listing->slug.'/edit')->assertOk();
    }

    public function test_sales_can_read_draft_details_with_media(): void
    {
        $listing = Listing::factory()->create();
        $listing->media()->create(['kind' => 'photo', 'disk' => 'public', 'object_key' => 'listings/draft.jpg', 'mime_type' => 'image/jpeg', 'position' => 0]);
        $this->actingAs(User::factory()->create());

        $this->get('/listings/'.$listing->slug)->assertOk()
            ->assertSee($listing->title)
            ->assertSee('listings/draft.jpg')
            ->assertDontSee('Ubah properti');
    }

    public function test_sales_cannot_call_editor_actions_directly(): void
    {
        $listing = Listing::factory()->create();
        $media = $listing->media()->create(['kind' => 'photo', 'disk' => 'public', 'object_key' => 'listings/auth.jpg', 'mime_type' => 'image/jpeg', 'position' => 0]);
        $sales = User::factory()->create();
        $admin = User::factory()->create(['role' => 'admin']);
        foreach ([
            ['save'], ['publish'], ['archive'], ['restoreDraft'], ['setAvailability', 'sold'],
            ['savePhotos'], ['saveFloorplan'], ['removeMedia', $media->id],
            ['makePrimary', $media->id], ['movePhoto', $media->id, 1],
        ] as $call) {
            $action = array_shift($call);
            auth()->login($admin);
            $editor = Livewire::test('pages::listings.editor', ['listing' => $listing]);
            auth()->login($sales);
            $editor->call($action, ...$call)->assertForbidden();
        }

        $this->assertSame($listing->title, $listing->fresh()->title);
        $this->assertSame('draft', $listing->fresh()->publication_status);
        $this->assertDatabaseHas('listing_media', ['id' => $media->id]);
    }

    public function test_admin_can_create_update_publish_and_change_availability(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        Livewire::test('pages::listings.editor')
            ->set('title', 'Rumah Contoh Baru')
            ->set('type', 'rumah')
            ->set('priceIdr', '850000000')
            ->set('province', 'Jawa Barat')
            ->set('city', 'Bandung')
            ->set('landAreaM2', '100')
            ->set('buildingAreaM2', '90')
            ->set('rooms', '3')
            ->set('description', 'Rumah sintetis untuk pengujian.')
            ->call('save')->assertHasNoErrors();

        $listing = Listing::where('title', 'Rumah Contoh Baru')->firstOrFail();
        $this->assertSame('draft', $listing->publication_status);

        Livewire::test('pages::listings.editor', ['listing' => $listing])
            ->call('publish')->assertHasErrors(['publication']);

        $listing->media()->create(['kind' => 'photo', 'disk' => 'public', 'object_key' => 'listings/test.jpg', 'mime_type' => 'image/jpeg', 'position' => 0]);
        Livewire::test('pages::listings.editor', ['listing' => $listing])
            ->call('publish')->assertHasNoErrors();
        $this->assertSame('published', $listing->fresh()->publication_status);

        Livewire::test('pages::listings.editor', ['listing' => $listing])
            ->call('setAvailability', 'sold')->assertHasNoErrors();
        $this->assertSame('sold', $listing->fresh()->availability_status);
    }

    public function test_invalid_type_and_status_injection_are_rejected(): void
    {
        $listing = Listing::factory()->create();
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        Livewire::test('pages::listings.editor', ['listing' => $listing])
            ->set('type', 'apartemen')->call('save')->assertHasErrors(['type']);

        $this->expectException(PublicPropertyNotFoundException::class);
        Livewire::test('pages::listings.editor', ['listing' => $listing])
            ->set('publicationStatus', 'published');
    }

    public function test_changing_property_to_land_clears_building_and_room_facts(): void
    {
        $listing = Listing::factory()->create();
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        Livewire::test('pages::listings.editor', ['listing' => $listing])
            ->set('type', 'tanah')
            ->call('save')->assertHasNoErrors();

        $this->assertNull($listing->fresh()->building_area_m2);
        $this->assertNull($listing->fresh()->rooms);
        $this->assertNull($listing->fresh()->bathrooms);
    }

    public function test_zero_coordinates_are_saved_as_coordinates(): void
    {
        $listing = Listing::factory()->create();
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        Livewire::test('pages::listings.editor', ['listing' => $listing])
            ->set('latitude', '0')
            ->set('longitude', '0')
            ->call('save')->assertHasNoErrors();

        $this->assertNotNull($listing->fresh()->latitude);
        $this->assertNotNull($listing->fresh()->longitude);
    }

    public function test_numeric_inputs_respect_database_column_limits(): void
    {
        $listing = Listing::factory()->create();
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        Livewire::test('pages::listings.editor', ['listing' => $listing])
            ->set('landAreaM2', '100000000')
            ->call('save')->assertHasErrors(['landAreaM2']);
        Livewire::test('pages::listings.editor', ['listing' => $listing])
            ->set('buildingAreaM2', '100000000')
            ->call('save')->assertHasErrors(['buildingAreaM2']);
        Livewire::test('pages::listings.editor', ['listing' => $listing])
            ->set('rooms', '32768')
            ->call('save')->assertHasErrors(['rooms']);
        Livewire::test('pages::listings.editor', ['listing' => $listing])
            ->set('bathrooms', '32768')
            ->call('save')->assertHasErrors(['bathrooms']);
    }

    public function test_archived_listing_can_return_to_draft(): void
    {
        $listing = Listing::factory()->create(['publication_status' => 'published']);
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        Livewire::test('pages::listings.editor', ['listing' => $listing])->call('archive')->assertHasNoErrors();
        $this->assertSame('archived', $listing->fresh()->publication_status);

        Livewire::test('pages::listings.editor', ['listing' => $listing])->call('restoreDraft')->assertHasNoErrors();
        $this->assertSame('draft', $listing->fresh()->publication_status);
    }

    public function test_published_listing_cannot_be_saved_without_required_details(): void
    {
        $listing = Listing::factory()->create(['publication_status' => 'published']);
        $listing->media()->create(['kind' => 'photo', 'disk' => 'public', 'object_key' => 'listings/required.jpg', 'mime_type' => 'image/jpeg', 'position' => 0]);
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        Livewire::test('pages::listings.editor', ['listing' => $listing])
            ->set('description', '')
            ->call('save')->assertHasErrors(['description']);

        $this->assertSame('published', $listing->fresh()->publication_status);
        $this->assertNotNull($listing->fresh()->description);
    }

    public function test_archived_listing_must_return_to_draft_before_republishing(): void
    {
        $listing = Listing::factory()->create(['publication_status' => 'archived']);
        $listing->media()->create(['kind' => 'photo', 'disk' => 'public', 'object_key' => 'listings/required.jpg', 'mime_type' => 'image/jpeg', 'position' => 0]);
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        Livewire::test('pages::listings.editor', ['listing' => $listing])
            ->call('publish')->assertHasErrors(['publication']);
        $this->assertSame('archived', $listing->fresh()->publication_status);
    }

    public function test_admin_can_upload_and_reorder_photos_and_sales_cannot_remove_them(): void
    {
        config()->set('listings.media_disk', 'public');
        Storage::fake('public');
        $listing = Listing::factory()->create();
        $admin = User::factory()->create(['role' => 'admin']);
        $sales = User::factory()->create();
        $this->actingAs($admin);

        Livewire::test('pages::listings.editor', ['listing' => $listing])
            ->set('newPhotos', [UploadedFile::fake()->image('front.jpg'), UploadedFile::fake()->image('rear.jpg')])
            ->call('savePhotos')->assertHasNoErrors();

        $photos = $listing->media()->where('kind', 'photo')->get();
        $this->assertCount(2, $photos);
        Livewire::test('pages::listings.editor', ['listing' => $listing])
            ->call('makePrimary', $photos[1]->id)->assertHasNoErrors();
        $this->assertSame($photos[1]->id, $listing->media()->where('kind', 'photo')->first()->id);

        $editor = Livewire::test('pages::listings.editor', ['listing' => $listing]);
        auth()->login($sales);
        $editor->call('removeMedia', $photos[1]->id)->assertForbidden();
        $this->assertDatabaseHas('listing_media', ['id' => $photos[1]->id]);
    }
}
