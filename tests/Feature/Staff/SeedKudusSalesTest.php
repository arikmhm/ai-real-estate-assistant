<?php

namespace Tests\Feature\Staff;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeedKudusSalesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app()->detectEnvironment(fn () => 'local');
    }

    public function test_local_command_creates_three_unapproved_sales_and_is_idempotent(): void
    {
        $this->artisan('demo:seed-kudus-sales')->assertSuccessful();
        $emails = ['alya.puspitasari@example.test', 'bagas.pradana@example.test', 'rizky.mahendra@example.test'];
        $this->assertDatabaseCount('users', 3);
        $passwords = User::orderBy('email')->pluck('password')->all();
        foreach ($emails as $email) {
            $sales = User::where('email', $email)->firstOrFail();
            $this->assertSame('sales', $sales->role->value);
            $this->assertTrue($sales->is_active);
            $this->assertNull($sales->whatsapp_number);
            $this->assertNull($sales->contact_email);
            $this->assertFalse($sales->public_contact_approved);
        }

        $this->artisan('demo:seed-kudus-sales')->assertSuccessful();
        $this->assertDatabaseCount('users', 3);
        $this->assertSame($passwords, User::orderBy('email')->pluck('password')->all());
    }

    public function test_command_rejects_nonlocal_environment_and_email_collision(): void
    {
        app()->detectEnvironment(fn () => 'production');
        $this->artisan('demo:seed-kudus-sales')->assertFailed();
        $this->assertDatabaseCount('users', 0);

        app()->detectEnvironment(fn () => 'local');
        User::factory()->create(['role' => 'admin', 'email' => 'alya.puspitasari@example.test']);
        $this->artisan('demo:seed-kudus-sales')->assertFailed();
        $this->assertDatabaseCount('users', 1);
    }
}
