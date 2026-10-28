<?php

namespace Tests\Feature;

use App\Models\Listing;
use App\Models\Prospect;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_visit_the_dashboard(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('dashboard'));
        $response->assertOk();
    }

    public function test_admin_sees_status_and_pipeline_counts_including_inactive_sales_history(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $active = User::factory()->create(['name' => 'Sales Aktif']);
        $inactive = User::factory()->create(['name' => 'Sales Nonaktif', 'is_active' => false]);
        $draft = Listing::factory()->create();
        $published = Listing::factory()->create(['publication_status' => 'published', 'availability_status' => 'reserved']);
        Listing::factory()->create(['publication_status' => 'archived', 'availability_status' => 'sold']);
        $this->prospect($draft, $active, 'Prospek Pertama', 'baru');
        $this->prospect($published, $active, 'Prospek Kedua', 'kunjungan');
        $this->prospect($published, $inactive, 'Prospek Historis', 'berhasil');

        $this->actingAs($admin)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Status publikasi')
            ->assertSee('Ketersediaan')
            ->assertSee('Prospek per tahap')
            ->assertSee('Prospek per sales')
            ->assertSee('Sales Aktif')
            ->assertSee('Sales Nonaktif')
            ->assertViewHas('publicationCounts', ['draft' => 1, 'published' => 1, 'archived' => 1])
            ->assertViewHas('availabilityCounts', ['available' => 1, 'reserved' => 1, 'sold' => 1])
            ->assertViewHas('stageCounts', fn (array $counts) => $counts['baru'] === 1 && $counts['kunjungan'] === 1 && $counts['berhasil'] === 1 && $counts['gagal'] === 0)
            ->assertViewHas('salesCounts', fn ($sales) => $sales->firstWhere('id', $active->id)->prospects_count === 2 && $sales->firstWhere('id', $inactive->id)->prospects_count === 1);
    }

    public function test_sales_sees_only_current_owned_prospects_followups_and_visits(): void
    {
        $now = Carbon::parse('2026-10-28 09:00:00', 'Asia/Jakarta');
        $this->travelTo($now);
        $first = User::factory()->create();
        $second = User::factory()->create();
        $listing = Listing::factory()->create();
        $mine = $this->prospect($listing, $first, 'Milik Saya', 'dihubungi', '2026-10-28');
        $this->prospect($listing, $first, 'Sudah Berhasil', 'berhasil', '2026-10-27');
        $other = $this->prospect($listing, $second, 'Rahasia Sales Lain', 'baru', '2026-10-27');
        $this->visit($mine, $listing, $first, $now->copy()->addHour());
        $this->visit($other, $listing, $second, $now->copy()->addHours(2));

        $this->actingAs($first)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Milik Saya')
            ->assertDontSee('Rahasia Sales Lain')
            ->assertDontSee('Prospek per sales')
            ->assertViewHas('prospectTotal', 2)
            ->assertViewHas('dueCount', 1)
            ->assertViewHas('upcomingCount', 1);

        $mine->update(['owner_id' => $second->id]);
        $this->actingAs($first)->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Milik Saya')
            ->assertViewHas('prospectTotal', 1)
            ->assertViewHas('dueCount', 0)
            ->assertViewHas('upcomingCount', 0);
    }

    public function test_dashboard_excludes_finished_and_past_agenda_and_handles_empty_state(): void
    {
        $now = Carbon::parse('2026-10-28 09:00:00', 'Asia/Jakarta');
        $this->travelTo($now);
        $admin = User::factory()->create(['role' => 'admin']);
        $sales = User::factory()->create();
        $listing = Listing::factory()->create();
        $this->actingAs($sales)->get(route('dashboard'))
            ->assertOk()->assertSee('Belum ada tindak lanjut jatuh tempo')
            ->assertSee('Belum ada kunjungan mendatang');

        $prospect = $this->prospect($listing, $sales, 'Akan Ditindaklanjuti', 'baru', '2026-10-29');
        $this->visit($prospect, $listing, $sales, $now->copy()->subHour());
        $this->visit($prospect, $listing, $sales, $now->copy()->addHour(), 'dibatalkan');
        $this->visit($prospect, $listing, $sales, $now->copy()->addHours(2));

        $this->actingAs($admin)->get(route('dashboard'))
            ->assertOk()
            ->assertViewHas('dueCount', 0)
            ->assertViewHas('upcomingCount', 1)
            ->assertSee('Akan Ditindaklanjuti');
    }

    private function prospect(Listing $listing, User $owner, string $name, string $stage, ?string $followupOn = null): Prospect
    {
        return Prospect::create([
            'listing_id' => $listing->id, 'owner_id' => $owner->id, 'name' => $name,
            'phone_e164' => '+6281234567890', 'source' => 'Form contoh',
            'stage' => $stage, 'followup_on' => $followupOn,
        ]);
    }

    private function visit(Prospect $prospect, Listing $listing, User $creator, Carbon $when, string $status = 'dijadwalkan'): void
    {
        Visit::create([
            'prospect_id' => $prospect->id, 'listing_id' => $listing->id,
            'created_by' => $creator->id, 'updated_by' => $creator->id,
            'scheduled_at' => $when->copy()->utc(), 'status' => $status,
        ]);
    }
}
