<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class SeedKudusSales extends Command
{
    protected $signature = 'demo:seed-kudus-sales';

    protected $description = 'Buat tiga akun sales sintetis hanya di development lokal';

    public function handle(): int
    {
        if (! app()->environment('local')) {
            $this->error('Perintah ini hanya boleh dijalankan saat APP_ENV=local.');

            return self::FAILURE;
        }

        $profiles = [
            ['name' => 'Alya Puspitasari', 'email' => 'alya.puspitasari@example.test'],
            ['name' => 'Bagas Pradana', 'email' => 'bagas.pradana@example.test'],
            ['name' => 'Rizky Mahendra', 'email' => 'rizky.mahendra@example.test'],
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

            $password = Str::random(32);
            User::create([
                'name' => $profile['name'],
                'email' => $profile['email'],
                'password' => $password,
                'role' => UserRole::Sales,
                'is_active' => true,
                'whatsapp_number' => null,
                'contact_email' => null,
                'public_contact_approved' => false,
            ]);

            $created++;
            $this->line("{$profile['email']} | kata sandi: {$password}");
        }

        $this->info($created > 0
            ? 'Akun sales development siap. Simpan kata sandi yang baru ditampilkan secara privat.'
            : 'Semua akun sales development sudah ada; tidak ada kata sandi baru.');

        return self::SUCCESS;
    }
}
