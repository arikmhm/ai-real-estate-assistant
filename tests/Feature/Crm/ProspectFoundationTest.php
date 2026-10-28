<?php

namespace Tests\Feature\Crm;

use App\Models\Listing;
use App\Models\Prospect;
use App\Models\ProspectActivity;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class ProspectFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_prospect_history_and_visits_survive_sales_deactivation(): void
    {
        $sales = User::factory()->create();
        $listing = Listing::factory()->create(['publication_status' => 'published']);
        $prospect = Prospect::create([
            'listing_id' => $listing->id, 'owner_id' => $sales->id, 'name' => 'Prospek Sintetis',
            'phone_e164' => '+6281234567890', 'source' => 'WhatsApp', 'stage' => 'baru',
        ]);
        ProspectActivity::create(['prospect_id' => $prospect->id, 'actor_id' => $sales->id, 'type' => 'note', 'body' => 'Menanyakan rumah.']);
        Visit::create(['prospect_id' => $prospect->id, 'listing_id' => $listing->id, 'created_by' => $sales->id,
            'updated_by' => $sales->id, 'scheduled_at' => now()->addDay(), 'status' => 'dijadwalkan']);
        $sales->update(['is_active' => false]);

        $this->assertSame(1, $prospect->activities()->count());
        $this->assertSame(1, $prospect->visits()->count());
        $this->assertSame($listing->id, $prospect->visits()->firstOrFail()->listing_id);
    }

    public function test_owner_and_admin_can_access_but_other_sales_cannot(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $admin = User::factory()->create(['role' => 'admin']);
        $listing = Listing::factory()->create();
        $prospect = Prospect::create(['listing_id' => $listing->id, 'owner_id' => $owner->id, 'name' => 'Rahasia',
            'phone_e164' => '+6281234567890', 'source' => 'Telepon', 'stage' => 'baru']);

        $this->assertTrue(Gate::forUser($owner)->allows('view', $prospect));
        $this->assertTrue(Gate::forUser($admin)->allows('view', $prospect));
        $this->assertFalse(Gate::forUser($other)->allows('view', $prospect));
        $this->assertSame(0, Prospect::visibleTo($other)->count());
        $this->assertSame(1, Prospect::visibleTo($admin)->count());
    }
}
