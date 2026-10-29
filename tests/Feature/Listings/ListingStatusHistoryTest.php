<?php

namespace Tests\Feature\Listings;

use App\Models\Listing;
use App\Models\ListingStatusChange;
use App\Models\User;
use App\Services\ListingStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

class ListingStatusHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_availability_change_records_actor_time_and_values_visible_in_editor(): void
    {
        $this->travelTo(Carbon::parse('2026-10-28 02:00:00 UTC'));
        $admin = User::factory()->create(['name' => 'Admin Audit', 'role' => 'admin']);
        $listing = Listing::factory()->create(['publication_status' => 'published']);
        $this->actingAs($admin);

        Livewire::test('pages::listings.editor', ['listing' => $listing])
            ->call('setAvailability', 'reserved')
            ->assertHasNoErrors()
            ->assertSeeInOrder(['Tersedia', 'Dipesan'])
            ->assertSee('Admin Audit · 28/10/2026 09:00');

        $this->assertSame('reserved', $listing->fresh()->availability_status);
        $this->assertDatabaseHas('listing_status_changes', [
            'listing_id' => $listing->id,
            'actor_id' => $admin->id,
            'field' => 'availability_status',
            'from_value' => 'available',
            'to_value' => 'reserved',
        ]);
        $this->assertNotNull(DB::table('listing_status_changes')->first()->created_at);
    }

    public function test_publication_transitions_keep_actor_and_old_new_values_in_order(): void
    {
        $firstAdmin = User::factory()->create(['role' => 'admin']);
        $secondAdmin = User::factory()->create(['role' => 'admin']);
        $listing = Listing::factory()->create();
        $listing->media()->create([
            'kind' => 'photo', 'disk' => 'public', 'object_key' => 'listings/history.jpg',
            'mime_type' => 'image/jpeg', 'position' => 0,
        ]);

        $this->actingAs($firstAdmin);
        Livewire::test('pages::listings.editor', ['listing' => $listing])->call('publish')->assertHasNoErrors();
        $this->actingAs($secondAdmin);
        Livewire::test('pages::listings.editor', ['listing' => $listing])->call('archive')->assertHasNoErrors();
        Livewire::test('pages::listings.editor', ['listing' => $listing])->call('restoreDraft')->assertHasNoErrors();

        $this->assertSame('draft', $listing->fresh()->publication_status);
        $this->assertSame([
            [$firstAdmin->id, 'draft', 'published'],
            [$secondAdmin->id, 'published', 'archived'],
            [$secondAdmin->id, 'archived', 'draft'],
        ], DB::table('listing_status_changes')->where('listing_id', $listing->id)->orderBy('id')
            ->get()->map(fn ($change) => [$change->actor_id, $change->from_value, $change->to_value])->all());
    }

    public function test_rejected_and_repeated_status_actions_do_not_create_history(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $listing = Listing::factory()->create();
        $this->actingAs($admin);
        $editor = Livewire::test('pages::listings.editor', ['listing' => $listing]);

        $editor->call('publish')->assertHasErrors(['publication']);
        $editor->call('setAvailability', 'unknown')->assertHasErrors(['availability']);
        $editor->call('setAvailability', 'available');
        $this->assertDatabaseCount('listing_status_changes', 0);

        $editor->call('setAvailability', 'reserved');
        $editor->call('setAvailability', 'reserved');
        $this->assertDatabaseCount('listing_status_changes', 1);
        $this->assertSame('reserved', $listing->fresh()->availability_status);
    }

    public function test_history_insert_failure_rolls_back_listing_status(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $listing = Listing::factory()->create();
        ListingStatusChange::creating(fn () => throw new RuntimeException('history insert failed'));

        try {
            app(ListingStatusService::class)->setAvailability($admin, $listing, 'sold');
            $this->fail('The status changed without its history.');
        } catch (RuntimeException $exception) {
            $this->assertSame('history insert failed', $exception->getMessage());
        } finally {
            ListingStatusChange::flushEventListeners();
        }

        $this->assertSame('available', $listing->fresh()->availability_status);
        $this->assertDatabaseCount('listing_status_changes', 0);
    }
}
