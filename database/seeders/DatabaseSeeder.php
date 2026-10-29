<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed synthetic demo CRM data. No WithoutModelEvents: Listing relies on model events for slugs.
     */
    public function run(): void
    {
        if (! app()->environment('local')) {
            throw new RuntimeException('Seeder demo hanya boleh dijalankan saat APP_ENV=local.');
        }

        $this->call(DemoSeeder::class);
    }
}
