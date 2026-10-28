<?php

namespace Tests\Feature\Staff;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Exceptions\PublicPropertyNotFoundException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ManageSalesTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_admin_can_open_staff_page(): void
    {
        $this->get('/staff')->assertRedirect(route('login'));

        $this->actingAs(User::factory()->create())
            ->get('/staff')->assertForbidden();

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get('/staff')->assertOk();
    }

    public function test_sales_cannot_call_staff_actions_directly(): void
    {
        $sales = User::factory()->create();
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        $component = Livewire::test('pages::staff.index')
            ->set('newName', 'Injected')
            ->set('newEmail', 'injected@example.test')
            ->set('newWhatsAppNumber', '+6281234567890')
            ->set('newPassword', 'secret-long-password')
            ->set('newPasswordConfirmation', 'secret-long-password');

        auth()->login($sales);
        $component->call('createSales')->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'injected@example.test']);
    }

    public function test_admin_creates_sales_with_hashed_temporary_password(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        Livewire::test('pages::staff.index')
            ->set('newName', 'Sales Satu')
            ->set('newEmail', 'sales@example.test')
            ->set('newWhatsAppNumber', '+6281234567890')
            ->set('newContactEmail', 'kontak@example.test')
            ->set('newPassword', 'secret-long-password')
            ->set('newPasswordConfirmation', 'secret-long-password')
            ->call('createSales')
            ->assertHasNoErrors();

        $sales = User::where('email', 'sales@example.test')->firstOrFail();
        $this->assertSame('sales', $sales->role->value);
        $this->assertTrue($sales->is_active);
        $this->assertSame('+6281234567890', $sales->whatsapp_number);
        $this->assertSame('kontak@example.test', $sales->contact_email);
        $this->assertNotSame('secret-long-password', $sales->password);

        auth()->logout();
        $this->post(route('login.store'), [
            'email' => 'sales@example.test',
            'password' => 'secret-long-password',
        ])->assertRedirect(route('dashboard', absolute: false));
        $this->get(route('dashboard'))->assertOk();
    }

    public function test_sales_email_is_normalized_for_login(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        Livewire::test('pages::staff.index')
            ->set('newName', 'Sales Dua')
            ->set('newEmail', 'SALES@EXAMPLE.TEST')
            ->set('newWhatsAppNumber', '+6281234567890')
            ->set('newPassword', 'secret-long-password')
            ->set('newPasswordConfirmation', 'secret-long-password')
            ->call('createSales')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('users', ['email' => 'sales@example.test', 'role' => 'sales']);
    }

    public function test_admin_can_create_profile_without_public_contact_and_approve_only_after_number_is_valid(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        Livewire::test('pages::staff.index')
            ->set('newName', 'Sales Tanpa Kontak')
            ->set('newEmail', 'tanpa@example.test')
            ->set('newPassword', 'secret-long-password')
            ->set('newPasswordConfirmation', 'secret-long-password')
            ->call('createSales')->assertHasNoErrors();
        $sales = User::where('email', 'tanpa@example.test')->firstOrFail();

        Livewire::test('pages::staff.index')->call('toggleContactApproval', $sales->id)->assertHasErrors();
        $this->assertFalse($sales->fresh()->public_contact_approved);

        Livewire::test('pages::staff.index')->call('startEdit', $sales->id)
            ->set('editWhatsAppNumber', '+6281234567890')->call('updateSales')->assertHasNoErrors();
        Livewire::test('pages::staff.index')->call('toggleContactApproval', $sales->id)->assertHasNoErrors();
        $this->assertTrue($sales->fresh()->public_contact_approved);
        Livewire::test('pages::staff.index')->call('toggleContactApproval', $sales->id)->assertHasNoErrors();
        $this->assertFalse($sales->fresh()->public_contact_approved);
    }

    public function test_editing_approved_contact_revokes_approval(): void
    {
        $sales = User::factory()->create(['whatsapp_number' => '+6281234567890', 'contact_email' => 'lama@example.test', 'public_contact_approved' => true]);
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        Livewire::test('pages::staff.index')->call('startEdit', $sales->id)
            ->set('editContactEmail', 'baru@example.test')->call('updateSales')->assertHasNoErrors();

        $this->assertFalse($sales->fresh()->public_contact_approved);
    }

    public function test_contact_change_on_user_model_revokes_approval(): void
    {
        $sales = User::factory()->create(['whatsapp_number' => '+6281234567890', 'public_contact_approved' => true]);
        $sales->update(['whatsapp_number' => '+6281234567891']);

        $this->assertFalse($sales->fresh()->public_contact_approved);
    }

    public function test_admin_edits_only_sales_profile_and_contact(): void
    {
        $sales = User::factory()->create(['whatsapp_number' => '+6281234567890']);
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        Livewire::test('pages::staff.index')
            ->call('startEdit', $sales->id)
            ->set('editName', 'Sales Baru')
            ->set('editEmail', 'baru@example.test')
            ->set('editWhatsAppNumber', '+6289876543210')
            ->set('editContactEmail', 'publik@example.test')
            ->call('updateSales')
            ->assertHasNoErrors();

        $this->assertSame('Sales Baru', $sales->fresh()->name);
        $this->assertSame('baru@example.test', $sales->fresh()->email);
        $this->assertSame('+6289876543210', $sales->fresh()->whatsapp_number);
        $this->assertSame('publik@example.test', $sales->fresh()->contact_email);
        $this->assertSame('sales', $sales->fresh()->role->value);
        $this->assertNull($sales->fresh()->email_verified_at);
    }

    public function test_admin_can_cancel_an_edit_without_changing_the_sales(): void
    {
        $sales = User::factory()->create();
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        Livewire::test('pages::staff.index')
            ->call('startEdit', $sales->id)
            ->call('cancelEdit')
            ->assertSet('editingSalesId', null);

        $this->assertSame($sales->name, $sales->fresh()->name);
    }

    public function test_deactivation_keeps_sales_record_and_revokes_database_sessions(): void
    {
        $sales = User::factory()->create(['whatsapp_number' => '+6281234567890']);
        DB::table('sessions')->insert([
            'id' => 'existing-session', 'user_id' => $sales->id,
            'payload' => '', 'last_activity' => time(),
        ]);
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        Livewire::test('pages::staff.index')->call('toggleActive', $sales->id)->assertHasNoErrors();

        $this->assertFalse($sales->fresh()->is_active);
        $this->assertDatabaseMissing('sessions', ['id' => 'existing-session']);
        $this->assertDatabaseHas('users', ['id' => $sales->id]);

        Livewire::test('pages::staff.index')->call('toggleActive', $sales->id)->assertHasNoErrors();
        $this->assertTrue($sales->fresh()->is_active);
    }

    public function test_admin_cannot_edit_or_deactivate_another_admin_through_staff_page(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        Livewire::test('pages::staff.index')->call('startEdit', $admin->id)->assertForbidden();
        Livewire::test('pages::staff.index')->call('toggleActive', $admin->id)->assertForbidden();

        $this->assertTrue($admin->fresh()->is_active);
    }

    public function test_invalid_whatsapp_and_duplicate_email_are_rejected(): void
    {
        $existing = User::factory()->create();
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        Livewire::test('pages::staff.index')
            ->set('newName', 'Sales')
            ->set('newEmail', $existing->email)
            ->set('newWhatsAppNumber', '081234567890')
            ->set('newPassword', 'secret-long-password')
            ->set('newPasswordConfirmation', 'secret-long-password')
            ->call('createSales')
            ->assertHasErrors(['newEmail', 'newWhatsAppNumber']);
    }

    #[DataProvider('unapprovedFields')]
    public function test_staff_form_rejects_injected_role_or_status(string $field, mixed $value): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        $this->expectException(PublicPropertyNotFoundException::class);
        Livewire::test('pages::staff.index')->set($field, $value);
    }

    public static function unapprovedFields(): array
    {
        return [
            'role' => ['role', 'admin'],
            'status' => ['is_active', false],
        ];
    }
}
