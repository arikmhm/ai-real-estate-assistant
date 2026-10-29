<?php

namespace Tests\Feature;

use App\Models\Listing;
use App\Models\ListingMedia;
use App\Models\ListingStatusChange;
use App\Models\Prospect;
use App\Models\User;
use App\Models\Visit;
use App\Support\ProspectPhone;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use RuntimeException;
use Tests\TestCase;

class DemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_seeder_adds_consistent_crm_history_without_touching_media(): void
    {
        $this->demoInventory();

        $this->seed(DemoSeeder::class);

        $this->assertGreaterThan(60, Prospect::count());
        $this->assertSame(5, Listing::count());
        $this->assertSame(5, ListingMedia::count());
        $this->assertTrue(Prospect::pluck('phone_e164')->every(fn (string $phone) => ProspectPhone::normalize($phone) === $phone));
        $this->assertSame([], array_diff(Prospect::distinct()->pluck('stage')->all(), Prospect::STAGES));
        $this->assertSame([], array_diff(Visit::distinct()->pluck('status')->all(), Visit::STATUSES));
        $this->assertTrue(Visit::where('status', 'dijadwalkan')->where('scheduled_at', '>=', now())->whereDate('scheduled_at', today())->exists());
        $this->assertFalse(Visit::where('status', 'dijadwalkan')->where('scheduled_at', '<', now())->exists());
        foreach (User::activeSales()->get() as $sales) {
            $this->assertGreaterThanOrEqual(2, Visit::where('status', 'dijadwalkan')
                ->whereHas('prospect', fn ($query) => $query->where('owner_id', $sales->id))->count(), $sales->name);
        }
        $this->assertSame(['available' => 3, 'reserved' => 1, 'sold' => 1], Listing::query()->selectRaw('availability_status, COUNT(*) as total')
            ->groupBy('availability_status')->orderBy('availability_status')->pluck('total', 'availability_status')->map(fn ($total) => (int) $total)->all());
        $this->assertSame(1, Prospect::where('stage', 'berhasil')->count());
        $this->assertSame(1, Prospect::where('stage', 'booking')->count());
        $sold = Listing::where('availability_status', 'sold')->firstOrFail();
        $this->assertSame(['reserved', 'sold'], $sold->statusChanges()->reorder('created_at')->pluck('to_value')->all());
        $this->assertFalse(Prospect::where('listing_id', $sold->id)->whereNotIn('stage', ['berhasil', 'gagal'])->exists());
        $this->assertFalse(Prospect::query()->whereHas('activities', fn ($query) => $query->where('created_at', '>', now()))->exists());

        $this->expectException(RuntimeException::class);
        $this->seed(DemoSeeder::class);
    }

    public function test_seed_crm_command_reseeds_only_with_fresh_and_only_locally(): void
    {
        $this->demoInventory();
        app()->detectEnvironment(fn () => 'local');

        $this->artisan('demo:seed-crm')->assertSuccessful();
        $counts = [Prospect::count(), Visit::count()];
        $this->artisan('demo:seed-crm')->assertFailed();
        $this->artisan('demo:seed-crm --fresh')->assertSuccessful();

        $this->assertSame($counts, [Prospect::count(), Visit::count()]);
        $this->assertSame(3, ListingStatusChange::count());
        $this->assertSame(5, ListingMedia::count());
        $this->assertSame(1, Listing::where('availability_status', 'sold')->count());

        app()->detectEnvironment(fn () => 'production');
        $this->artisan('demo:seed-crm --fresh')->assertFailed();
        $this->assertSame($counts, [Prospect::count(), Visit::count()]);
    }

    public function test_database_seeder_refuses_outside_local(): void
    {
        $this->expectException(RuntimeException::class);
        $this->seed(DatabaseSeeder::class);
    }

    /** One admin, three sales, and five published listings with one photo each, at 10.00 WIB. */
    private function demoInventory(): void
    {
        $this->travelTo(Carbon::parse('2026-10-29 10:00', 'Asia/Jakarta'));
        User::factory()->create(['role' => 'admin']);
        User::factory()->count(3)->create();
        collect(['rumah', 'rumah', 'rumah', 'tanah', 'kos'])->each(fn (string $type, int $index) => Listing::factory()->create([
            'type' => $type, 'price_idr' => 500_000_000 + $index * 100_000_000, 'publication_status' => 'published',
        ])->media()->create(['kind' => 'photo', 'disk' => 'r2', 'object_key' => 'listings/'.$index.'.webp', 'mime_type' => 'image/webp']));
    }
}
