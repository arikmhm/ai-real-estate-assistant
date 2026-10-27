<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class CreateAdmin extends Command
{
    protected $signature = 'users:create-admin';

    protected $description = 'Buat akun admin melalui prompt interaktif';

    public function handle(): int
    {
        if (! $this->input->isInteractive()) {
            $this->error('Perintah ini memerlukan terminal interaktif.');

            return self::FAILURE;
        }

        $name = $this->ask('Nama admin');
        $email = Str::lower(trim($this->ask('Email admin') ?? ''));
        $password = $this->secret('Kata sandi');
        $confirmation = $this->secret('Ulangi kata sandi');

        $validator = Validator::make([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'password_confirmation' => $confirmation,
        ], [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', Password::default(), 'confirmed'],
        ]);

        if ($validator->fails()) {
            $this->error($validator->errors()->first());

            return self::FAILURE;
        }

        User::create([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'role' => UserRole::Admin,
            'is_active' => true,
        ]);

        $this->info('Akun admin berhasil dibuat.');

        return self::SUCCESS;
    }
}
