<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Listing;
use App\Models\Prospect;
use App\Models\ProspectActivity;
use App\Models\User;
use App\Models\Visit;
use Carbon\CarbonImmutable as Carbon;
use Faker\Factory as FakerFactory;
use Faker\Generator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Synthetic CRM history (prospects, activities, visits, status changes) on top of the existing
 * demo listings and sales. Listings and users are never created here, so no new images are needed.
 */
class DemoSeeder extends Seeder
{
    /** New prospects per month, oldest first; the last entry is the current month. */
    private const MONTHLY_PROSPECTS = [7, 9, 12, 14, 17, 21];

    private const SOURCES = [
        'Instagram' => 26, 'Website' => 20, 'Iklan WhatsApp' => 15, 'Facebook Ads' => 12,
        'Rumah123' => 10, 'Referensi' => 10, 'Walk-in / spanduk' => 7,
    ];

    private const OPENING_NOTES = [
        'Menanyakan harga nego dan status sertifikat.',
        'Tertarik, minta denah dan foto bagian dalam.',
        'Tanya simulasi KPR BTN dengan DP 20%.',
        'Mencari rumah dekat sekolah anak, minta info akses jalan.',
        'Minta dikirimi lokasi Google Maps.',
        'Ingin tahu biaya balik nama dan pajak.',
        'Masih membandingkan dengan properti lain di sekitar Kudus.',
        'Pembelian tunai bertahap, tanya skema pembayarannya.',
    ];

    private const VISIT_NOTES = [
        'rumah' => ['Datang bersama pasangan, cek kamar dan dapur.', 'Bawa simulasi KPR untuk dibahas di lokasi.', 'Cek kondisi atap dan saluran air.'],
        'tanah' => ['Cek batas patok dan lebar akses jalan.', 'Bawa salinan sertifikat untuk dicocokkan.', 'Lihat kontur tanah dan drainase.'],
        'kos' => ['Survei kamar dan data okupansi enam bulan terakhir.', 'Cek meteran listrik per kamar.', 'Bahas potensi sewa tahunan.'],
    ];

    private const AFTER_VISIT_NOTES = [
        'Suka lokasi, minta waktu diskusi dengan keluarga.',
        'Cocok dengan tata ruang, akan ajukan penawaran.',
        'Merasa harga sedikit di atas anggaran.',
        'Minta jadwal survei ulang bersama orang tua.',
    ];

    private const LOST_NOTES = [
        'Anggaran belum cukup, menunda pembelian.',
        'Memilih properti lain yang lebih dekat tempat kerja.',
        'Tidak merespons setelah tiga kali dihubungi.',
        'Pengajuan KPR belum disetujui bank.',
    ];

    private const FIRST_NAMES = [
        'Agus', 'Ahmad', 'Anisa', 'Ayu', 'Bambang', 'Budi', 'Dewi', 'Dian', 'Dwi', 'Eko', 'Fajar', 'Fitri',
        'Galih', 'Hendra', 'Heru', 'Indah', 'Joko', 'Kurniawati', 'Lestari', 'Lukman', 'Mega', 'Muhammad',
        'Novi', 'Nur', 'Purnomo', 'Putri', 'Rahmat', 'Ratna', 'Rina', 'Rizal', 'Siti', 'Sri', 'Sugeng',
        'Teguh', 'Tri', 'Umi', 'Wahyu', 'Wulan', 'Yuni', 'Zaenal',
    ];

    private const LAST_NAMES = [
        'Santoso', 'Wibowo', 'Setiawan', 'Pratama', 'Hidayat', 'Saputra', 'Susanti', 'Rahmawati', 'Nugroho',
        'Kurniawan', 'Purnamasari', 'Hartono', 'Wijaya', 'Utomo', 'Prasetyo', 'Firmansyah', 'Maulana',
        'Handayani', 'Ramadhan', 'Mulyani', 'Sutrisno', 'Haryanto', 'Anggraini', 'Fauzi',
    ];

    private Generator $faker;

    private int $upcomingVisits = 0;

    private Carbon $now;

    /** @var list<string> */
    private array $usedPhones = [];

    public function run(): void
    {
        $listings = Listing::query()->where('publication_status', 'published')->orderBy('id')->get();
        $sales = User::activeSales()->orderBy('id')->get();
        $admin = User::query()->where('role', UserRole::Admin->value)->orderBy('id')->first();
        if ($listings->count() < 4 || $sales->isEmpty() || ! $admin) {
            throw new RuntimeException('Butuh minimal 4 listing terbit, 1 sales aktif, dan 1 admin. Jalankan demo:import-kudus, demo:seed-kudus-sales, dan users:create-admin dahulu.');
        }
        if (Prospect::query()->exists()) {
            throw new RuntimeException('Tabel prospek sudah berisi; seeder demo hanya untuk CRM kosong.');
        }

        $this->faker = FakerFactory::create('id_ID');
        $this->faker->seed(2026);
        $this->now = Carbon::now('Asia/Jakarta')->startOfMinute();

        DB::transaction(fn () => $this->seed($listings, $sales, $admin));
    }

    /**
     * @param  Collection<int, Listing>  $listings
     * @param  Collection<int, User>  $sales
     */
    private function seed(Collection $listings, Collection $sales, User $admin): void
    {
        $start = $this->now->copy()->subMonths(count(self::MONTHLY_PROSPECTS) - 1)->startOfMonth();

        // Listings go live one by one over the first ~10 weeks of the history window.
        foreach ($listings->values() as $index => $listing) {
            $listing->timestamps = false;
            $listing->forceFill(['created_at' => $start->copy()->addDays(2 + $index * 9)->setTime(10, 0)->utc()])->save();
        }

        // The cheapest house sold, the next one is reserved; both leave the public catalog on those dates.
        $houses = $listings->where('type', 'rumah')->sortBy('price_idr')->values();
        $sold = $houses->get(0) ?? $listings->get(0);
        $reserved = $houses->get(1) ?? $listings->get(1);
        $soldWinnerAt = $this->workingTime($start->copy()->addDays(72));
        $soldReservedAt = $soldWinnerAt->copy()->addDays(34);
        $soldAt = $soldWinnerAt->copy()->addDays(56);
        $reservedWinnerAt = $this->workingTime($this->now->copy()->subDays(38));
        $reservedAt = $reservedWinnerAt->copy()->addDays(18);
        $closedAt = [$sold->id => $soldReservedAt, $reserved->id => $reservedAt];

        $weights = $listings->mapWithKeys(fn (Listing $listing) => [$listing->id => match (true) {
            $listing->id === $sold->id => 4,
            $listing->type === 'kos' => 3,
            $listing->type === 'tanah' => 1,
            default => 3,
        }])->all();

        foreach (self::MONTHLY_PROSPECTS as $offset => $count) {
            $month = $start->copy()->addMonths($offset);
            $lastDay = $offset === count(self::MONTHLY_PROSPECTS) - 1 ? $this->now->day - 1 : $month->daysInMonth;
            for ($i = 0; $i < $count; $i++) {
                $createdAt = $this->workingTime($month->copy()->day($this->faker->numberBetween(1, max(1, $lastDay))));
                $candidates = $listings->filter(fn (Listing $listing) => $listing->created_at->lt($createdAt)
                    && (! isset($closedAt[$listing->id]) || $createdAt->lt($closedAt[$listing->id])));
                if ($candidates->isEmpty()) {
                    continue;
                }
                $listing = $listings->firstWhere('id', $this->weighted(array_intersect_key($weights, $candidates->keyBy('id')->all())));
                $stage = $this->stageFor($createdAt, isset($closedAt[$listing->id]));
                $this->prospect($listing, $this->faker->randomElement($sales->all()), $createdAt, $stage, $closedAt[$listing->id] ?? null);
            }
        }

        $this->prospect($sold, $sales->first(), $soldWinnerAt, 'berhasil', null, ['reserved' => $soldReservedAt, 'sold' => $soldAt]);
        $this->prospect($reserved, $sales->last(), $reservedWinnerAt, 'booking', null, ['reserved' => $reservedAt]);

        $sold->forceFill(['availability_status' => 'sold'])->save();
        $reserved->forceFill(['availability_status' => 'reserved'])->save();
        foreach ([[$sold, 'available', 'reserved', $soldReservedAt], [$sold, 'reserved', 'sold', $soldAt], [$reserved, 'available', 'reserved', $reservedAt]] as [$listing, $from, $to, $at]) {
            $listing->statusChanges()->create([
                'actor_id' => $admin->id, 'field' => 'availability_status',
                'from_value' => $from, 'to_value' => $to, 'created_at' => $at->copy()->addHour()->utc(),
            ]);
        }
    }

    /** Stage a prospect has reached today, given its age; booking and berhasil belong to the scripted winners only. */
    private function stageFor(Carbon $createdAt, bool $unitClosing): string
    {
        $age = (int) $createdAt->diffInDays($this->now);
        $stage = match (true) {
            $age < 4 => $this->weighted(['baru' => 7, 'dihubungi' => 3]),
            $age < 14 => $this->weighted(['baru' => 2, 'dihubungi' => 4, 'kunjungan' => 3, 'gagal' => 1]),
            $age < 40 => $this->weighted(['dihubungi' => 3, 'kunjungan' => 3, 'negosiasi' => 2, 'gagal' => 2]),
            default => $this->weighted(['dihubungi' => 2, 'kunjungan' => 2, 'negosiasi' => 1, 'gagal' => 5]),
        };

        // Every other buyer of a unit that has been reserved or sold is closed as lost.
        return $unitClosing ? 'gagal' : $stage;
    }

    /**
     * @param  array<string, Carbon>  $unitEvents  reserved/sold dates when this prospect is the winner
     */
    private function prospect(Listing $listing, User $owner, Carbon $createdAt, string $stage, ?Carbon $unitClosedAt, array $unitEvents = []): void
    {
        $open = ! in_array($stage, ['berhasil', 'gagal'], true);
        $prospect = Prospect::query()->forceCreate([
            'listing_id' => $listing->id, 'owner_id' => $owner->id,
            'name' => $this->faker->randomElement(self::FIRST_NAMES).' '.$this->faker->randomElement(self::LAST_NAMES),
            'phone_e164' => $this->phone(), 'source' => $this->weighted(self::SOURCES), 'stage' => $stage,
            'followup_on' => $open ? $this->now->copy()->addDays($this->faker->numberBetween(-5, 14))->toDateString() : null,
            'created_at' => $createdAt->copy()->utc(), 'updated_at' => $createdAt->copy()->utc(),
        ]);

        $at = $createdAt->copy();
        $this->activity($prospect, $owner, $at, 'created', 'Prospek dibuat.');
        $at = $at->addMinutes($this->faker->numberBetween(5, 90));
        $this->activity($prospect, $owner, $at, 'note', $this->faker->randomElement(self::OPENING_NOTES));

        // Walk the funnel in order; a lost prospect drops out after a random intermediate stage.
        $target = $stage === 'gagal' ? $this->faker->randomElement(['baru', 'dihubungi', 'dihubungi', 'kunjungan', 'negosiasi']) : $stage;
        $path = array_slice(Prospect::STAGES, 0, array_search($target, Prospect::STAGES, true) + 1);
        if ($stage === 'gagal') {
            $path[] = 'gagal';
        }

        $deadline = $unitEvents['sold'] ?? $unitEvents['reserved'] ?? $this->now->copy()->subHour();
        $steps = max(1, count($path) - 1);
        $span = max(60, (int) $at->diffInMinutes($unitClosedAt && $unitClosedAt->lt($deadline) ? $unitClosedAt : $deadline));
        $previous = 'baru';
        foreach (array_slice($path, 1) as $index => $next) {
            $at = $this->officeHours($at->addMinutes((int) ($span / ($steps + 1)) + $this->faker->numberBetween(0, 120)));
            if ($next === 'kunjungan') {
                $at = $this->visits($prospect, $listing, $owner, $at, $stage === 'kunjungan');
            }
            if ($next === 'gagal' && $unitClosedAt) {
                $at = $unitClosedAt->copy()->addDays($this->faker->numberBetween(1, 3));
            }
            if ($next === 'gagal') {
                $body = $unitClosedAt ? 'Unit sudah dipesan pembeli lain, prospek ditutup.' : $this->faker->randomElement(self::LOST_NOTES);
                $this->activity($prospect, $owner, $at, 'note', $body);
            }
            if ($next === 'booking' && isset($unitEvents['reserved'])) {
                $at = $unitEvents['reserved']->copy();
            }
            if ($next === 'berhasil' && isset($unitEvents['sold'])) {
                $at = $unitEvents['sold']->copy();
            }
            if ($at->gt($this->now)) {
                break;
            }
            $this->activity($prospect, $owner, $at, 'stage_changed', 'Tahap prospek diperbarui.', ['from' => $previous, 'to' => $next]);
            $previous = $next;
        }

        if ($previous !== $stage) {
            $prospect->forceFill(['stage' => $previous])->save();
        }

        // Recent contacted prospects often already have a survey booked.
        if ($stage === 'dihubungi' && $createdAt->diffInDays($this->now) < 14 && $this->faker->boolean(45)) {
            $this->visits($prospect, $listing, $owner, $this->now->copy(), true);
        }
    }

    /** Creates the visit(s) for reaching the "kunjungan" stage and returns when the stage moved on. */
    private function visits(Prospect $prospect, Listing $listing, User $owner, Carbon $stageAt, bool $mayBeUpcoming): Carbon
    {
        $upcoming = $mayBeUpcoming && $this->faker->boolean(70);
        // The first few booked surveys land today so the agenda always has something on it.
        $scheduledAt = $upcoming
            ? $this->now->addDays($this->upcomingVisits++ < 3 ? 0 : $this->faker->numberBetween(1, 9))
            : $stageAt->addDays($this->faker->numberBetween(1, 4));
        $scheduledAt = $scheduledAt->setTime($this->faker->numberBetween(9, 15), $this->faker->randomElement([0, 30]));
        if (! $upcoming && $scheduledAt->gt($this->now)) {
            $scheduledAt = $this->now->copy()->subDay()->setTime(10, 0);
        }

        $cancelled = ! $upcoming && $this->faker->boolean(15);
        $this->visit($prospect, $listing, $owner, $stageAt, $scheduledAt, $upcoming ? 'dijadwalkan' : ($cancelled ? 'dibatalkan' : 'selesai'));
        if ($cancelled) {
            // A cancelled survey is usually rebooked a few days later.
            $scheduledAt = $scheduledAt->copy()->addDays($this->faker->numberBetween(2, 5));
            $status = $scheduledAt->lt($this->now) ? 'selesai' : 'dijadwalkan';
            $this->visit($prospect, $listing, $owner, $scheduledAt->copy()->subDays(2), $scheduledAt, $status);
        }
        if (! $upcoming && $scheduledAt->lt($this->now)) {
            $this->activity($prospect, $owner, $scheduledAt->copy()->addHours(3), 'note', $this->faker->randomElement(self::AFTER_VISIT_NOTES));
        }

        // A booked survey moves the stage right away; a past one only after it took place.
        return $upcoming ? $stageAt : $stageAt->max($scheduledAt->copy()->addHours(4));
    }

    private function visit(Prospect $prospect, Listing $listing, User $owner, Carbon $bookedAt, Carbon $scheduledAt, string $status): void
    {
        $bookedAt = $bookedAt->min($this->now);
        $visit = Visit::query()->forceCreate([
            'prospect_id' => $prospect->id, 'listing_id' => $listing->id,
            'created_by' => $owner->id, 'updated_by' => $owner->id,
            'scheduled_at' => $scheduledAt->copy()->utc(), 'status' => $status,
            'location' => $this->faker->boolean(80) ? 'Lokasi unit, '.$listing->district : 'Kantor pemasaran Aditamaland, Kudus',
            'notes' => $this->faker->randomElement(self::VISIT_NOTES[$listing->type] ?? self::VISIT_NOTES['rumah']),
            'created_at' => $bookedAt->copy()->utc(),
            'updated_at' => ($status === 'dijadwalkan' ? $bookedAt : $scheduledAt->copy()->addHours(2)->min($this->now))->copy()->utc(),
        ]);
        $this->activity($prospect, $owner, $bookedAt, 'visit_created', 'Kunjungan dijadwalkan.', ['visit_id' => $visit->id]);
        if ($status !== 'dijadwalkan' && $scheduledAt->lt($this->now)) {
            $this->activity($prospect, $owner, $scheduledAt->copy()->addHours(2)->min($this->now), 'visit_updated', 'Kunjungan diperbarui.', [
                'visit_id' => $visit->id, 'from' => ['status' => 'dijadwalkan'], 'to' => ['status' => $status],
            ]);
        }
    }

    /** @param array<string, mixed>|null $details */
    private function activity(Prospect $prospect, User $actor, Carbon $at, string $type, string $body, ?array $details = null): void
    {
        ProspectActivity::query()->forceCreate([
            'prospect_id' => $prospect->id, 'actor_id' => $actor->id, 'type' => $type,
            'body' => $body, 'details' => $details, 'created_at' => $at->copy()->min($this->now)->utc(),
        ]);
    }

    /** Synthetic +62800… numbers: valid for ProspectPhone, but not a mobile range, so they reach nobody. */
    private function phone(): string
    {
        do {
            $phone = '+62800'.$this->faker->numerify('########');
        } while (in_array($phone, $this->usedPhones, true));

        return $this->usedPhones[] = $phone;
    }

    private function officeHours(Carbon $at): Carbon
    {
        return match (true) {
            $at->hour < 8 => $at->setTime($this->faker->numberBetween(8, 10), $this->faker->numberBetween(0, 59)),
            $at->hour > 20 => $at->addDay()->setTime($this->faker->numberBetween(8, 10), $this->faker->numberBetween(0, 59)),
            default => $at,
        };
    }

    private function workingTime(Carbon $day): Carbon
    {
        $time = $day->copy()->setTime($this->faker->numberBetween(8, 20), $this->faker->numberBetween(0, 59));

        return $time->min($this->now->copy()->subHours(2));
    }

    /**
     * @template TKey of array-key
     *
     * @param  array<TKey, int>  $weights
     * @return TKey
     */
    private function weighted(array $weights): int|string
    {
        $pick = $this->faker->numberBetween(1, array_sum($weights));
        foreach ($weights as $key => $weight) {
            if (($pick -= $weight) <= 0) {
                return $key;
            }
        }

        return array_key_first($weights);
    }
}
