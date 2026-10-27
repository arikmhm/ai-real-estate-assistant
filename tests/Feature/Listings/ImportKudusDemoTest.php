<?php

namespace Tests\Feature\Listings;

use App\Models\Listing;
use App\Models\ListingMedia;
use App\Services\ListingMediaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class ImportKudusDemoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app()->detectEnvironment(fn () => 'local');
        config()->set('listings.media_disk', 'r2');
        Storage::fake('r2');
    }

    public function test_check_validates_dataset_without_creating_records_or_objects(): void
    {
        $this->artisan('demo:import-kudus', ['--check' => true])->assertSuccessful();

        $this->assertDatabaseCount('listings', 0);
        $this->assertDatabaseCount('listing_media', 0);
        $this->assertSame([], Storage::disk('r2')->allFiles('listings'));
    }

    public function test_apply_imports_eight_published_listings_with_three_photos_and_one_floorplan_each(): void
    {
        $this->artisan('demo:import-kudus', ['--apply' => true])->assertSuccessful();

        $this->assertSame(8, Listing::where('publication_status', 'published')->count());
        $this->assertSame(24, ListingMedia::where('kind', 'photo')->count());
        $this->assertSame(8, ListingMedia::where('kind', 'floorplan')->count());
        $this->assertCount(32, Storage::disk('r2')->allFiles('listings'));
        $this->assertSame(['image/webp'], ListingMedia::distinct()->pluck('mime_type')->all());
        $this->assertSame([0, 1, 2], Listing::where('slug', 'rumah-keluarga-satu-lantai-kota-kudus')->firstOrFail()
            ->media()->where('kind', 'photo')->pluck('position')->all());
    }

    public function test_repeating_apply_skips_complete_listings_without_duplicating_media(): void
    {
        $this->artisan('demo:import-kudus', ['--apply' => true])->assertSuccessful();
        $firstKeys = ListingMedia::orderBy('object_key')->pluck('object_key')->all();

        $this->artisan('demo:import-kudus', ['--apply' => true])->assertSuccessful();

        $this->assertDatabaseCount('listings', 8);
        $this->assertDatabaseCount('listing_media', 32);
        $this->assertSame($firstKeys, ListingMedia::orderBy('object_key')->pluck('object_key')->all());
        $this->assertCount(32, Storage::disk('r2')->allFiles('listings'));
    }

    public function test_repeating_apply_does_not_change_a_sold_or_manually_edited_complete_listing(): void
    {
        $this->artisan('demo:import-kudus', ['--apply' => true])->assertSuccessful();
        $listing = Listing::where('slug', 'rumah-keluarga-satu-lantai-kota-kudus')->firstOrFail();
        $listing->update(['price_idr' => 1250000000, 'availability_status' => 'sold', 'address' => 'Catatan internal']);

        $this->artisan('demo:import-kudus', ['--apply' => true])->assertSuccessful();

        $this->assertSame(1250000000, $listing->fresh()->price_idr);
        $this->assertSame('sold', $listing->fresh()->availability_status);
        $this->assertSame('Catatan internal', $listing->fresh()->address);
    }

    public function test_apply_reports_extra_manual_media_without_deleting_it(): void
    {
        $this->artisan('demo:import-kudus', ['--apply' => true])->assertSuccessful();
        $listing = Listing::where('slug', 'rumah-keluarga-satu-lantai-kota-kudus')->firstOrFail();
        $extra = app(ListingMediaService::class)->addPhoto($listing, UploadedFile::fake()->image('tambahan.webp'));

        $this->artisan('demo:import-kudus', ['--apply' => true])->assertFailed();

        $this->assertSame('published', $listing->fresh()->publication_status);
        $this->assertDatabaseHas('listing_media', ['id' => $extra->id]);
        Storage::disk('r2')->assertExists($extra->object_key);
    }

    public function test_apply_rebuilds_a_partial_listing_without_touching_complete_listings(): void
    {
        $this->artisan('demo:import-kudus', ['--apply' => true])->assertSuccessful();
        $partial = Listing::where('slug', 'rumah-keluarga-satu-lantai-kota-kudus')->firstOrFail();
        $other = Listing::where('slug', 'rumah-dua-lantai-jati-kudus')->firstOrFail();
        $otherKeys = $other->media()->pluck('object_key')->all();
        app(ListingMediaService::class)->remove($partial->media()->where('kind', 'photo')->firstOrFail());

        $this->artisan('demo:import-kudus', ['--apply' => true])->assertSuccessful();

        $this->assertSame('published', $partial->fresh()->publication_status);
        $this->assertSame(3, $partial->media()->where('kind', 'photo')->count());
        $this->assertSame(1, $partial->media()->where('kind', 'floorplan')->count());
        $this->assertSame($otherKeys, $other->media()->pluck('object_key')->all());
        $this->assertCount(32, Storage::disk('r2')->allFiles('listings'));
    }

    public function test_failed_media_upload_leaves_only_that_listing_as_draft_and_can_resume(): void
    {
        app()->instance(ListingMediaService::class, new class extends ListingMediaService
        {
            private int $calls = 0;

            public function addPhoto(Listing $listing, UploadedFile $file): ListingMedia
            {
                if (++$this->calls === 2) {
                    throw new RuntimeException('simulated upload failure');
                }

                return parent::addPhoto($listing, $file);
            }
        });

        $this->artisan('demo:import-kudus', ['--apply' => true])->assertFailed();

        $this->assertSame('draft', Listing::where('slug', 'rumah-keluarga-satu-lantai-kota-kudus')->firstOrFail()->publication_status);
        $this->assertSame(7, Listing::where('publication_status', 'published')->count());

        app()->forgetInstance(ListingMediaService::class);
        $this->artisan('demo:import-kudus', ['--apply' => true])->assertSuccessful();

        $this->assertSame(8, Listing::where('publication_status', 'published')->count());
        $this->assertDatabaseCount('listing_media', 32);
    }

    public function test_failed_fake_disk_write_leaves_listing_as_draft_and_can_resume(): void
    {
        $manager = Storage::getFacadeRoot();
        $fakeDisk = Storage::disk('r2');
        $disk = Mockery::mock($fakeDisk)->makePartial();
        $writes = 0;
        $disk->shouldReceive('putFileAs')->andReturnUsing(function (...$args) use ($fakeDisk, &$writes) {
            if (++$writes === 2) {
                return false;
            }

            return $fakeDisk->putFileAs(...$args);
        });
        Storage::shouldReceive('disk')->with('r2')->andReturn($disk);

        $this->artisan('demo:import-kudus', ['--apply' => true])->assertFailed();
        $this->assertSame('draft', Listing::where('slug', 'rumah-keluarga-satu-lantai-kota-kudus')->firstOrFail()->publication_status);

        Storage::swap($manager);
        $this->artisan('demo:import-kudus', ['--apply' => true])->assertSuccessful();
        $this->assertSame(8, Listing::where('publication_status', 'published')->count());
        $this->assertDatabaseCount('listing_media', 32);
    }

    public function test_apply_rejects_nonlocal_environment_or_non_r2_disk(): void
    {
        app()->detectEnvironment(fn () => 'production');
        $this->artisan('demo:import-kudus', ['--apply' => true])->assertFailed();
        $this->assertDatabaseCount('listings', 0);

        app()->detectEnvironment(fn () => 'local');
        config()->set('listings.media_disk', 'public');
        $this->artisan('demo:import-kudus', ['--apply' => true])->assertFailed();
        $this->assertDatabaseCount('listings', 0);
    }

    public function test_replace_reuploads_media_and_removes_old_objects(): void
    {
        $this->artisan('demo:import-kudus', ['--apply' => true])->assertSuccessful();
        $oldKeys = ListingMedia::pluck('object_key')->all();

        $this->artisan('demo:import-kudus', ['--apply' => true, '--replace' => true])->assertSuccessful();

        $this->assertDatabaseCount('listings', 8);
        $this->assertDatabaseCount('listing_media', 32);
        foreach ($oldKeys as $oldKey) {
            Storage::disk('r2')->assertMissing($oldKey);
        }
        $this->assertCount(32, Storage::disk('r2')->allFiles('listings'));
    }
}
