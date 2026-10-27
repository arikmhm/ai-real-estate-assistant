<?php

namespace Tests\Feature\Listings;

use App\Models\Listing;
use App\Models\ListingMedia;
use App\Services\ListingMediaService;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class ListingMediaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('listings.media_disk', 'public');
        Storage::fake('public');
    }

    public function test_photo_uploads_use_generated_key_and_first_is_primary(): void
    {
        $listing = Listing::factory()->create();
        $service = app(ListingMediaService::class);

        $first = $service->addPhoto($listing, UploadedFile::fake()->image('front.jpg'));
        $second = $service->addPhoto($listing, UploadedFile::fake()->image('rear.png'));

        Storage::disk('public')->assertExists([$first->object_key, $second->object_key]);
        $this->assertSame(0, $first->position);
        $this->assertSame(1, $second->position);
        $this->assertNotSame('front.jpg', basename($first->object_key));
    }

    public function test_storage_write_failure_does_not_create_media_record(): void
    {
        $listing = Listing::factory()->create();
        $disk = Mockery::mock(FilesystemAdapter::class);
        $disk->shouldReceive('putFileAs')->once()->andReturn(false);
        Storage::shouldReceive('disk')->with('public')->once()->andReturn($disk);

        $this->expectException(RuntimeException::class);
        try {
            app(ListingMediaService::class)->addPhoto($listing, UploadedFile::fake()->image('front.jpg'));
        } finally {
            $this->assertDatabaseCount('listing_media', 0);
        }
    }

    public function test_invalid_photo_and_oversized_floorplan_are_rejected(): void
    {
        $listing = Listing::factory()->create();
        $service = app(ListingMediaService::class);

        try {
            $service->addPhoto($listing, UploadedFile::fake()->create('bad.pdf', 50, 'application/pdf'));
            $this->fail('PDF was accepted as a photo.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('listing_media', 0);
        }

        $this->expectException(ValidationException::class);
        $service->setFloorplan($listing, UploadedFile::fake()->create('large.pdf', 10241, 'application/pdf'));
    }

    public function test_more_than_twenty_photos_are_rejected_without_writing_a_file(): void
    {
        $listing = Listing::factory()->create();
        for ($position = 0; $position < 20; $position++) {
            $listing->media()->create([
                'kind' => 'photo', 'disk' => 'public', 'object_key' => 'listings/existing-'.$position.'.jpg',
                'mime_type' => 'image/jpeg', 'position' => $position,
            ]);
        }

        $this->expectException(ValidationException::class);
        try {
            app(ListingMediaService::class)->addPhoto($listing, UploadedFile::fake()->image('extra.jpg'));
        } finally {
            $this->assertDatabaseCount('listing_media', 20);
            $this->assertSame([], Storage::disk('public')->allFiles('listings'));
        }
    }

    public function test_floorplan_pdf_replaces_previous_floorplan(): void
    {
        $listing = Listing::factory()->create();
        $service = app(ListingMediaService::class);
        $old = $service->setFloorplan($listing, UploadedFile::fake()->create('one.pdf', 10, 'application/pdf'));
        $new = $service->setFloorplan($listing, UploadedFile::fake()->create('two.pdf', 10, 'application/pdf'));

        $this->assertSame('application/pdf', $new->mime_type);
        $this->assertDatabaseCount('listing_media', 1);
        Storage::disk('public')->assertMissing($old->object_key);
        Storage::disk('public')->assertExists($new->object_key);
    }

    public function test_database_failure_after_upload_removes_new_object(): void
    {
        $listing = Listing::factory()->create();
        ListingMedia::creating(fn () => throw new RuntimeException('database insert failed'));

        try {
            app(ListingMediaService::class)->addPhoto($listing, UploadedFile::fake()->image('front.jpg'));
            $this->fail('Database failure was not raised.');
        } catch (RuntimeException $exception) {
            $this->assertSame('database insert failed', $exception->getMessage());
        } finally {
            ListingMedia::flushEventListeners();
        }

        $this->assertSame([], Storage::disk('public')->allFiles('listings'));
    }

    public function test_published_listing_keeps_its_last_photo(): void
    {
        $listing = Listing::factory()->create(['publication_status' => 'published']);
        $service = app(ListingMediaService::class);
        $photo = $service->addPhoto($listing, UploadedFile::fake()->image('front.jpg'));

        $this->expectException(ValidationException::class);
        $service->remove($photo);
    }

    public function test_failed_object_deletion_leaves_no_media_reference_and_is_logged_for_pruning(): void
    {
        $listing = Listing::factory()->create();
        $media = $listing->media()->create(['kind' => 'photo', 'disk' => 'public', 'object_key' => 'listings/orphan.jpg', 'mime_type' => 'image/jpeg', 'position' => 0]);
        $disk = Mockery::mock(FilesystemAdapter::class);
        $disk->shouldReceive('delete')->once()->andThrow(new RuntimeException('remote unavailable'));
        Storage::shouldReceive('disk')->with('public')->once()->andReturn($disk);
        Log::shouldReceive('warning')->once();

        app(ListingMediaService::class)->remove($media);

        $this->assertDatabaseMissing('listing_media', ['id' => $media->id]);
    }

    public function test_admin_can_set_primary_photo_and_move_order(): void
    {
        $listing = Listing::factory()->create();
        $service = app(ListingMediaService::class);
        $one = $service->addPhoto($listing, UploadedFile::fake()->image('one.jpg'));
        $two = $service->addPhoto($listing, UploadedFile::fake()->image('two.jpg'));
        $three = $service->addPhoto($listing, UploadedFile::fake()->image('three.jpg'));

        $service->makePrimary($three);
        $this->assertSame([$three->id, $one->id, $two->id], $listing->media()->where('kind', 'photo')->pluck('id')->all());

        $service->movePhoto($two, -1);
        $this->assertSame([$three->id, $two->id, $one->id], $listing->media()->where('kind', 'photo')->pluck('id')->all());
    }

    public function test_orphan_pruning_previews_by_default_and_deletes_only_old_unreferenced_objects(): void
    {
        $listing = Listing::factory()->create();
        $referenced = app(ListingMediaService::class)->addPhoto($listing, UploadedFile::fake()->image('kept.jpg'));
        Storage::disk('public')->put('listings/old.jpg', 'old');
        Storage::disk('public')->put('listings/new.jpg', 'new');
        Storage::disk('public')->put('outside/old.jpg', 'outside');
        touch(Storage::disk('public')->path('listings/old.jpg'), time() - 90000);
        touch(Storage::disk('public')->path('outside/old.jpg'), time() - 90000);

        $this->artisan('listings:prune-media', ['--disk' => 'public'])->assertSuccessful();
        Storage::disk('public')->assertExists('listings/old.jpg');

        $this->artisan('listings:prune-media', ['--disk' => 'public', '--delete' => true])->assertSuccessful();
        Storage::disk('public')->assertMissing('listings/old.jpg');
        Storage::disk('public')->assertExists([$referenced->object_key, 'listings/new.jpg', 'outside/old.jpg']);
    }
}
