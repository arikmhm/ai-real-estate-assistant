<?php

namespace Tests\Feature\Staff;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AccountFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_existing_style_user_is_an_active_sales_without_admin_access(): void
    {
        $id = DB::table('users')->insertGetId([
            'name' => 'Pengguna lama',
            'email' => 'lama@example.test',
            'password' => 'unused-hash',
        ]);
        $user = User::findOrFail($id);

        $this->assertSame('sales', $user->role->value);
        $this->assertTrue($user->is_active);
        $this->assertFalse($user->isAdmin());
    }

    public function test_active_sales_scope_excludes_inactive_sales_and_admins(): void
    {
        $active = User::factory()->create(['name' => 'Active Sales', 'whatsapp_number' => '+6281234567890']);
        User::factory()->create(['is_active' => false, 'whatsapp_number' => '+6281234567891']);
        User::factory()->create(['role' => 'admin']);

        $this->assertSame([$active->id], User::activeSales()->pluck('id')->all());
    }

    public function test_admin_can_be_created_interactively_without_exposing_password(): void
    {
        $this->artisan('users:create-admin')
            ->expectsQuestion('Nama admin', 'Admin Awal')
            ->expectsQuestion('Email admin', 'admin@example.test')
            ->expectsQuestion('Kata sandi', 'secret-long-password')
            ->expectsQuestion('Ulangi kata sandi', 'secret-long-password')
            ->expectsOutput('Akun admin berhasil dibuat.')
            ->doesntExpectOutput('secret-long-password')
            ->assertExitCode(0);

        $admin = User::where('email', 'admin@example.test')->firstOrFail();
        $this->assertTrue($admin->isAdmin());
        $this->assertTrue($admin->is_active);
        $this->assertNotSame('secret-long-password', $admin->password);
    }

    public function test_duplicate_admin_email_is_rejected_without_creating_another_account(): void
    {
        User::factory()->create(['email' => 'admin@example.test']);

        $this->artisan('users:create-admin')
            ->expectsQuestion('Nama admin', 'Admin Baru')
            ->expectsQuestion('Email admin', 'ADMIN@EXAMPLE.TEST')
            ->expectsQuestion('Kata sandi', 'secret-long-password')
            ->expectsQuestion('Ulangi kata sandi', 'secret-long-password')
            ->assertExitCode(1);

        $this->assertSame(1, User::where('email', 'admin@example.test')->count());
    }

    public function test_admin_email_is_normalized_for_login(): void
    {
        $this->artisan('users:create-admin')
            ->expectsQuestion('Nama admin', 'Admin')
            ->expectsQuestion('Email admin', 'ADMIN@EXAMPLE.TEST')
            ->expectsQuestion('Kata sandi', 'secret-long-password')
            ->expectsQuestion('Ulangi kata sandi', 'secret-long-password')
            ->assertExitCode(0);

        $this->assertDatabaseHas('users', ['email' => 'admin@example.test', 'role' => 'admin']);
    }
}
