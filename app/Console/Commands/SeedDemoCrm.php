<?php

namespace App\Console\Commands;

use App\Models\Listing;
use App\Models\ListingStatusChange;
use App\Models\Prospect;
use App\Models\ProspectActivity;
use App\Models\Visit;
use Database\Seeders\DemoSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class SeedDemoCrm extends Command
{
    protected $signature = 'demo:seed-crm {--fresh : Hapus riwayat CRM dan status ketersediaan dahulu}';

    protected $description = 'Isi riwayat CRM sintetis (prospek, aktivitas, kunjungan) hanya di development lokal';

    public function handle(): int
    {
        if (! app()->environment('local')) {
            $this->error('Perintah ini hanya boleh dijalankan saat APP_ENV=local.');

            return self::FAILURE;
        }

        try {
            // One transaction: a failed seed never leaves the CRM wiped.
            DB::transaction(function (): void {
                if ($this->option('fresh')) {
                    ProspectActivity::query()->delete();
                    Visit::query()->delete();
                    Prospect::query()->delete();
                    ListingStatusChange::query()->delete();
                    Listing::query()->update(['availability_status' => 'available']);
                }
                $this->laravel->make(DemoSeeder::class)->setContainer($this->laravel)->setCommand($this)->__invoke();
            });
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf('Selesai: %d prospek, %d kunjungan, %d aktivitas.', Prospect::count(), Visit::count(), ProspectActivity::count()));

        return self::SUCCESS;
    }
}
