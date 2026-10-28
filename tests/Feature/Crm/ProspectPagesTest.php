<?php

namespace Tests\Feature\Crm;

use App\Models\Listing;
use App\Models\Prospect;
use App\Models\User;
use App\Services\ProspectService;
use App\Services\VisitService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProspectPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_routes_scope_private_prospects_and_keep_inactive_owner_history_for_admin(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $admin = User::factory()->create(['role' => 'admin']);
        $listing = Listing::factory()->create(['publication_status' => 'published']);
        $prospect = app(ProspectService::class)->create($owner, $this->data($listing));

        $this->get('/prospects')->assertRedirect(route('login'));
        $this->get('/visits')->assertRedirect(route('login'));
        $this->actingAs($other)->get('/prospects')->assertOk()->assertDontSee($prospect->name);
        $this->get('/prospects/'.$prospect->id)->assertForbidden();
        $this->actingAs($owner)->get('/prospects')->assertOk()->assertSee($prospect->name);
        $this->get('/prospects/'.$prospect->id)->assertOk()->assertSee($prospect->phone_e164);
        $listing->update(['publication_status' => 'archived']);
        $this->get('/prospects/'.$prospect->id)->assertOk()->assertSee($listing->title);
        $owner->update(['is_active' => false]);
        $this->actingAs($admin)->get('/prospects/'.$prospect->id)->assertOk()->assertSee($prospect->name);
    }

    public function test_create_form_warns_about_duplicate_before_explicit_save(): void
    {
        $first = User::factory()->create();
        $second = User::factory()->create();
        $listing = Listing::factory()->create(['publication_status' => 'published']);
        app(ProspectService::class)->create($first, $this->data($listing));
        $this->actingAs($second);

        $form = Livewire::test('pages::prospects.create')
            ->set('listingId', $listing->id)->set('name', 'Prospek Kedua')
            ->set('phone', '+62 812 3456 7890')->set('source', 'WhatsApp')
            ->call('save')->assertHasErrors(['duplicate']);
        $this->assertSame(1, Prospect::count());
        $form->call('saveAnyway')->assertHasNoErrors();
        $this->assertSame(2, Prospect::count());
    }

    public function test_admin_can_review_duplicate_matches_while_sales_only_sees_a_generic_warning(): void
    {
        $owner = User::factory()->create();
        $otherSales = User::factory()->create();
        $admin = User::factory()->create(['role' => 'admin']);
        $listing = Listing::factory()->create(['publication_status' => 'published']);
        $match = app(ProspectService::class)->create($owner, $this->data($listing, ['name' => 'Nama Prospek Terdahulu']));

        $this->actingAs($otherSales);
        Livewire::test('pages::prospects.create')
            ->set('listingId', $listing->id)->set('name', 'Prospek Kedua')
            ->set('phone', '081234567890')->set('source', 'Telepon')
            ->call('save')->assertHasErrors(['duplicate'])
            ->assertDontSee('Nama Prospek Terdahulu')
            ->assertDontSee(route('prospects.show', $match));

        $this->actingAs($admin);
        Livewire::test('pages::prospects.create')
            ->set('listingId', $listing->id)->set('ownerId', $otherSales->id)
            ->set('name', 'Prospek Admin')->set('phone', '+62 812 3456 7890')->set('source', 'Telepon')
            ->call('save')->assertHasErrors(['duplicate'])
            ->assertSee('Nama Prospek Terdahulu')
            ->assertSee(route('prospects.show', $match));

        $another = app(ProspectService::class)->create($otherSales, $this->data($listing, [
            'name' => 'Prospek Untuk Koreksi', 'phone' => '081299998888',
        ]));
        Livewire::test('pages::prospects.show', ['prospect' => $another])
            ->set('editPhone', '081234567890')->call('saveDetails')->assertHasErrors(['duplicate'])
            ->assertSee('Nama Prospek Terdahulu')
            ->assertSee(route('prospects.show', $match));
    }

    public function test_save_anyway_without_warning_cannot_skip_duplicate_review(): void
    {
        $sales = User::factory()->create();
        $listing = Listing::factory()->create(['publication_status' => 'published']);
        $this->actingAs($sales);

        Livewire::test('pages::prospects.create')
            ->set('listingId', $listing->id)->set('name', 'Prospek Baru')
            ->set('phone', '081234567890')->set('source', 'WhatsApp')
            ->call('saveAnyway')->assertHasErrors(['duplicate']);
        $this->assertSame(0, Prospect::count());
    }

    public function test_duplicate_confirmation_expires_when_form_changes(): void
    {
        $sales = User::factory()->create();
        $listing = Listing::factory()->create(['publication_status' => 'published']);
        app(ProspectService::class)->create($sales, $this->data($listing));
        $this->actingAs($sales);

        Livewire::test('pages::prospects.create')
            ->set('listingId', $listing->id)->set('name', 'Prospek Kedua')
            ->set('phone', '081234567890')->set('source', 'WhatsApp')
            ->call('save')->assertHasErrors(['duplicate'])
            ->set('phone', '081299998888')
            ->call('saveAnyway')->assertHasErrors(['duplicate']);
        $this->assertSame(1, Prospect::count());
    }

    public function test_admin_sees_missing_owner_error_on_owner_field(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $listing = Listing::factory()->create(['publication_status' => 'published']);
        $this->actingAs($admin);

        Livewire::test('pages::prospects.create')
            ->set('listingId', $listing->id)->set('name', 'Prospek Baru')
            ->set('phone', '081234567890')->set('source', 'WhatsApp')
            ->call('save')->assertHasErrors(['ownerId']);
    }

    public function test_invalid_visit_time_is_reported_on_visible_field(): void
    {
        $sales = User::factory()->create();
        $listing = Listing::factory()->create(['publication_status' => 'published']);
        $prospect = app(ProspectService::class)->create($sales, $this->data($listing));
        $this->actingAs($sales);

        Livewire::test('pages::prospects.show', ['prospect' => $prospect])
            ->set('visitAt', 'bukan-waktu')->call('scheduleVisit')->assertHasErrors(['visitAt']);
    }

    public function test_detail_actions_record_activity_stage_visit_and_admin_reassignment(): void
    {
        $owner = User::factory()->create();
        $newOwner = User::factory()->create();
        $admin = User::factory()->create(['role' => 'admin']);
        $listing = Listing::factory()->create(['publication_status' => 'published']);
        $prospect = app(ProspectService::class)->create($owner, $this->data($listing));
        $this->actingAs($owner);

        Livewire::test('pages::prospects.show', ['prospect' => $prospect])
            ->set('activityType', 'followup')->set('activityBody', 'Sudah menghubungi calon pembeli.')
            ->call('addActivity')->assertHasNoErrors()
            ->set('newStage', 'kunjungan')->call('changeStage')->assertHasNoErrors()
            ->set('visitAt', '2026-11-05T10:00')->set('visitLocation', 'Lokasi properti')
            ->call('scheduleVisit')->assertHasNoErrors();
        $this->assertSame(1, $prospect->visits()->count());
        $this->assertSame('kunjungan', $prospect->fresh()->stage);
        $this->assertSame(1, $prospect->activities()->where('type', 'followup')->count());
        $visit = $prospect->visits()->firstOrFail();
        Livewire::test('pages::prospects.show', ['prospect' => $prospect])
            ->call('startVisitEdit', $visit->id)
            ->set('visitStatus', 'selesai')->call('updateVisit')->assertHasNoErrors();
        $this->assertSame('selesai', $visit->fresh()->status);
        $this->actingAs($admin);
        Livewire::test('pages::prospects.show', ['prospect' => $prospect])
            ->set('newOwnerId', $newOwner->id)->call('reassign')->assertHasNoErrors();
        $this->assertSame($newOwner->id, $prospect->fresh()->owner_id);
        $this->actingAs($owner)->get('/prospects/'.$prospect->id)->assertForbidden();
    }

    public function test_old_owner_cannot_use_stale_component_after_reassignment(): void
    {
        $owner = User::factory()->create();
        $newOwner = User::factory()->create();
        $admin = User::factory()->create(['role' => 'admin']);
        $listing = Listing::factory()->create(['publication_status' => 'published']);
        $prospect = app(ProspectService::class)->create($owner, $this->data($listing));
        $this->actingAs($owner);
        $component = Livewire::test('pages::prospects.show', ['prospect' => $prospect])
            ->set('activityBody', 'Tidak boleh tersimpan');

        app(ProspectService::class)->reassign($admin, $prospect, $newOwner->id);
        $component->call('addActivity')->assertForbidden();
        $this->assertSame(0, $prospect->activities()->where('body', 'Tidak boleh tersimpan')->count());
    }

    public function test_visit_and_followup_index_only_show_accessible_items(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $listing = Listing::factory()->create(['publication_status' => 'published']);
        $prospect = app(ProspectService::class)->create($owner, $this->data($listing, ['followup_on' => '2026-11-05']));
        app(VisitService::class)->create($owner, $prospect, ['scheduled_at' => '2026-11-05T10:00', 'location' => 'Lokasi rahasia']);

        $this->actingAs($other)->get('/visits')->assertOk()->assertDontSee('Lokasi rahasia')->assertDontSee($prospect->phone_e164);
        $this->actingAs($owner)->get('/visits')->assertOk()->assertSee('Lokasi rahasia')->assertSee($prospect->name);
    }

    private function data(Listing $listing, array $overrides = []): array
    {
        return array_merge(['listing_id' => $listing->id, 'name' => 'Prospek Rahasia', 'phone' => '081234567890',
            'source' => 'WhatsApp', 'stage' => 'baru', 'followup_on' => null], $overrides);
    }
}
