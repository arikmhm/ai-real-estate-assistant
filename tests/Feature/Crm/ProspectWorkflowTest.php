<?php

namespace Tests\Feature\Crm;

use App\Models\Listing;
use App\Models\Prospect;
use App\Models\User;
use App\Services\ProspectService;
use App\Services\VisitService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ProspectWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_sales_create_normalizes_number_and_requires_confirmation_for_duplicate(): void
    {
        $listing = Listing::factory()->create(['publication_status' => 'published']);
        $firstSales = User::factory()->create();
        $secondSales = User::factory()->create();
        $service = app(ProspectService::class);
        $first = $service->create($firstSales, $this->data($listing, ['phone' => '0812-3456 7890']));
        $this->assertSame('+6281234567890', $first->phone_e164);
        $this->assertSame($firstSales->id, $first->owner_id);

        try {
            $service->create($secondSales, $this->data($listing, ['phone' => '+62 812 3456 7890']));
            $this->fail('Expected duplicate warning.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('duplicate', $exception->errors());
            $this->assertStringNotContainsString($firstSales->name, json_encode($exception->errors()));
        }
        $this->assertSame(1, Prospect::count());
        $second = $service->create($secondSales, $this->data($listing, ['phone' => '+62 812 3456 7890']), true);
        $this->assertNotSame($first->id, $second->id);
        $this->assertSame(2, Prospect::count());
    }

    public function test_admin_must_select_active_sales_and_listing_must_be_published(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $inactive = User::factory()->create(['is_active' => false]);
        $draft = Listing::factory()->create();
        $published = Listing::factory()->create(['publication_status' => 'published', 'availability_status' => 'sold']);
        $service = app(ProspectService::class);

        foreach ([
            $this->data($published),
            $this->data($published, ['owner_id' => $inactive->id]),
            $this->data($draft, ['owner_id' => User::factory()->create()->id]),
        ] as $invalid) {
            try {
                $service->create($admin, $invalid);
                $this->fail('Expected validation error.');
            } catch (ValidationException) {
                $this->assertSame(0, Prospect::count());
            }
        }
        $sales = User::factory()->create();
        $prospect = $service->create($admin, $this->data($published, ['owner_id' => $sales->id]));
        $this->assertSame($sales->id, $prospect->owner_id);
        $this->assertSame($published->id, $prospect->listing_id);
    }

    public function test_stage_and_assignment_changes_keep_history_without_changing_listing(): void
    {
        $listing = Listing::factory()->create(['publication_status' => 'published']);
        $owner = User::factory()->create();
        $newOwner = User::factory()->create();
        $admin = User::factory()->create(['role' => 'admin']);
        $service = app(ProspectService::class);
        $prospect = $service->create($owner, $this->data($listing));

        $service->changeStage($owner, $prospect, 'booking');
        $service->reassign($admin, $prospect, $newOwner->id);
        $this->assertSame('booking', $prospect->fresh()->stage);
        $this->assertSame('available', $listing->fresh()->availability_status);
        $this->assertFalse(Gate::forUser($owner)->allows('view', $prospect->fresh()));
        $this->assertTrue(Gate::forUser($newOwner)->allows('view', $prospect->fresh()));
        $this->assertDatabaseHas('prospect_activities', ['prospect_id' => $prospect->id, 'type' => 'stage_changed', 'actor_id' => $owner->id]);
        $this->assertDatabaseHas('prospect_activities', ['prospect_id' => $prospect->id, 'type' => 'owner_changed', 'actor_id' => $admin->id]);
        $assignment = $prospect->activities()->where('type', 'owner_changed')->firstOrFail();
        $this->assertSame($owner->name, $assignment->details['from_name']);
        $this->assertSame($newOwner->name, $assignment->details['to_name']);
        $owner->update(['is_active' => false]);
        $this->assertSame(2, $prospect->activities()->whereIn('type', ['stage_changed', 'owner_changed'])->count());
    }

    public function test_multiple_visits_and_updates_leave_an_audit_trail(): void
    {
        $listing = Listing::factory()->create(['publication_status' => 'published']);
        $sales = User::factory()->create();
        $prospect = app(ProspectService::class)->create($sales, $this->data($listing));
        $visits = app(VisitService::class);

        $first = $visits->create($sales, $prospect, ['scheduled_at' => '2026-11-05T10:00', 'location' => 'Kantor agensi', 'notes' => 'Pertemuan awal']);
        $visits->create($sales, $prospect, ['scheduled_at' => '2026-11-07T10:00', 'location' => 'Lokasi properti', 'notes' => 'Kunjungan ulang']);
        $visits->update($sales, $first, ['scheduled_at' => '2026-11-05T11:00', 'status' => 'selesai', 'location' => 'Kantor agensi', 'notes' => 'Selesai']);

        $this->assertSame(2, $prospect->visits()->count());
        $this->assertSame('selesai', $first->fresh()->status);
        $this->assertSame('2026-11-05 04:00', $first->fresh()->scheduled_at->utc()->format('Y-m-d H:i'));
        $this->assertSame(1, $prospect->activities()->where('type', 'visit_updated')->count());
        $this->assertSame('available', $listing->fresh()->availability_status);
    }

    public function test_editing_phone_checks_duplicate_again_and_preserves_listing(): void
    {
        $listing = Listing::factory()->create(['publication_status' => 'published']);
        $sales = User::factory()->create();
        $service = app(ProspectService::class);
        $first = $service->create($sales, $this->data($listing));
        $second = $service->create($sales, $this->data($listing, ['phone' => '081211112222']));

        try {
            $service->updateDetails($sales, $second, ['name' => 'Koreksi', 'phone' => $first->phone_e164,
                'source' => 'Telepon', 'followup_on' => '2026-11-06']);
            $this->fail('Expected duplicate warning.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('duplicate', $exception->errors());
        }
        $this->assertSame('+6281211112222', $second->fresh()->phone_e164);
        $service->updateDetails($sales, $second, ['name' => 'Koreksi', 'phone' => $first->phone_e164,
            'source' => 'Telepon', 'followup_on' => '2026-11-06'], true);
        $this->assertSame($first->phone_e164, $second->fresh()->phone_e164);
        $this->assertSame($listing->id, $second->fresh()->listing_id);
        $this->assertDatabaseHas('prospect_activities', ['prospect_id' => $second->id, 'type' => 'followup_changed']);
    }

    public function test_nonadmin_cannot_reassign_and_inactive_sales_cannot_be_target(): void
    {
        $listing = Listing::factory()->create(['publication_status' => 'published']);
        $sales = User::factory()->create();
        $inactive = User::factory()->create(['is_active' => false]);
        $admin = User::factory()->create(['role' => 'admin']);
        $service = app(ProspectService::class);
        $prospect = $service->create($sales, $this->data($listing));

        try {
            $service->reassign($sales, $prospect, $inactive->id);
            $this->fail('Sales reassigned a prospect.');
        } catch (AuthorizationException) {
            $this->assertSame($sales->id, $prospect->fresh()->owner_id);
        }
        try {
            $service->reassign($admin, $prospect, $inactive->id);
            $this->fail('Inactive sales received a prospect.');
        } catch (ValidationException) {
            $this->assertSame($sales->id, $prospect->fresh()->owner_id);
        }
    }

    public function test_old_owner_cannot_add_activity_using_a_stale_prospect_model(): void
    {
        $listing = Listing::factory()->create(['publication_status' => 'published']);
        $oldOwner = User::factory()->create();
        $newOwner = User::factory()->create();
        $admin = User::factory()->create(['role' => 'admin']);
        $service = app(ProspectService::class);
        $prospect = $service->create($oldOwner, $this->data($listing));

        $service->reassign($admin, $prospect, $newOwner->id);

        try {
            $service->addActivity($oldOwner, $prospect, 'note', 'Tidak boleh tersimpan');
            $this->fail('Former owner added activity through a stale model.');
        } catch (AuthorizationException) {
            $this->assertSame(0, $prospect->activities()->where('body', 'Tidak boleh tersimpan')->count());
        }
    }

    public function test_blank_name_or_source_is_rejected_before_saving(): void
    {
        $listing = Listing::factory()->create(['publication_status' => 'published']);
        $sales = User::factory()->create();
        foreach ([$this->data($listing, ['name' => '   ']), $this->data($listing, ['source' => '   '])] as $invalid) {
            try {
                app(ProspectService::class)->create($sales, $invalid);
                $this->fail('Blank data was saved.');
            } catch (ValidationException) {
                $this->assertSame(0, Prospect::count());
            }
        }
    }

    public function test_success_stage_does_not_mark_property_sold(): void
    {
        $listing = Listing::factory()->create(['publication_status' => 'published', 'availability_status' => 'available']);
        $sales = User::factory()->create();
        $service = app(ProspectService::class);
        $prospect = $service->create($sales, $this->data($listing));

        $service->changeStage($sales, $prospect, 'berhasil');

        $this->assertSame('berhasil', $prospect->fresh()->stage);
        $this->assertSame('available', $listing->fresh()->availability_status);
        $this->assertDatabaseHas('prospect_activities', ['prospect_id' => $prospect->id, 'type' => 'stage_changed']);
    }

    private function data(Listing $listing, array $overrides = []): array
    {
        return array_merge([
            'listing_id' => $listing->id, 'name' => 'Prospek Sintetis', 'phone' => '081234567890',
            'source' => 'WhatsApp', 'stage' => 'baru', 'followup_on' => null, 'note' => 'Tanya harga.',
        ], $overrides);
    }
}
