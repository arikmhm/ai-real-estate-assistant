<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Command;

class SeedKudusSales extends Command
{
    // Aman hanya karena perintah ini menolak berjalan di luar APP_ENV=local.
    private const DEV_PASSWORD = '12345678';

    protected $signature = 'demo:seed-kudus-sales';

    protected $description = 'Buat tiga akun sales sintetis berkontak publik hanya di development lokal';

    public function handle(): int
    {
        if (! app()->environment('local')) {
            $this->error('Perintah ini hanya boleh dijalankan saat APP_ENV=local.');

            return self::FAILURE;
        }

        // Synthetic contacts: the +62800 prefix is not an Indonesian mobile range, so wa.me links reach no real person.
        $profiles = [
            ['name' => 'Alya Puspitasari', 'email' => 'alya.puspitasari@example.test', 'whatsapp_number' => '+628000000001'],
            ['name' => 'Bagas Pradana', 'email' => 'bagas.pradana@example.test', 'whatsapp_number' => '+628000000002'],
            ['name' => 'Rizky Mahendra', 'email' => 'rizky.mahendra@example.test', 'whatsapp_number' => '+628000000003'],
        ];

        // Check every address before creating anything, so a collision cannot leave a partial set.
        $existing = User::query()->whereIn('email', array_column($profiles, 'email'))->get()->keyBy('email');
        $created = 0;
        foreach ($profiles as $profile) {
            $user = $existing->get($profile['email']);
            if ($user && ($user->name !== $profile['name'] || $user->role !== UserRole::Sales)) {
                $this->error("Email {$profile['email']} sudah digunakan oleh akun lain. Tidak ada akun yang diubah.");

                return self::FAILURE;
            }
        }

        foreach ($profiles as $profile) {
            if ($existing->has($profile['email'])) {
                $this->line("Lewati {$profile['name']}: akun sudah ada.");

                continue;
            }

            User::create([
                'name' => $profile['name'],
                'email' => $profile['email'],
                'password' => self::DEV_PASSWORD,
                'role' => UserRole::Sales,
                'is_active' => true,
                'whatsapp_number' => $profile['whatsapp_number'],
                'contact_email' => $profile['email'],
                'public_contact_approved' => true,
            ]);

            $created++;
            $this->line("{$profile['email']} | kata sandi: ".self::DEV_PASSWORD);
        }

        $this->info($created > 0
            ? 'Akun sales development siap.'
            : 'Semua akun sales development sudah ada; tidak ada akun baru.');

        return self::SUCCESS;
    }
}
