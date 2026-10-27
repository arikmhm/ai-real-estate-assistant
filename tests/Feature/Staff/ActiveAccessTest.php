<?php

namespace Tests\Feature\Staff;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Laravel\Passkeys\Passkey;
use Laravel\Passkeys\Passkeys;
use Tests\TestCase;

class ActiveAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_inactive_sales_cannot_log_in_with_correct_password(): void
    {
        $user = User::factory()->create(['is_active' => false]);

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_inactive_account_loses_access_on_its_next_request(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $user->update(['is_active' => false]);

        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_reactivated_sales_can_log_in_again(): void
    {
        $user = User::factory()->create(['is_active' => false]);
        $user->update(['is_active' => true]);

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticatedAs($user);
    }

    public function test_inactive_account_cannot_use_an_existing_passkey(): void
    {
        $user = User::factory()->create(['is_active' => false]);
        $passkey = new Passkey;
        $passkey->setRelation('user', $user);

        $this->assertFalse(Passkeys::allowsLogin(Request::create('/'), $passkey));
    }

    public function test_deactivation_during_two_factor_challenge_prevents_login(): void
    {
        $user = User::factory()->withTwoFactor()->create();
        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('two-factor.login'));

        $user->update(['is_active' => false]);

        $this->post(route('two-factor.login.store'), [
            'recovery_code' => 'recovery-code-1',
        ])->assertRedirect(route('login'));
        $this->assertGuest();
    }
}
