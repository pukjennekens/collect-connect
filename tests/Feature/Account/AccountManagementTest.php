<?php

declare(strict_types=1);

namespace Tests\Feature\Account;

use App\Models\User;
use Filament\Panel;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class AccountManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_account_entry_redirects_guests_and_customers_and_contact_uses_cms_route(): void
    {
        $this->get('/account')->assertRedirect(route('login'));
        $this->get('/contact')->assertRedirect('/pages/contact');
        $this->actingAs(User::factory()->create())->get('/account')->assertRedirect(route('account.orders.index'));
        $this->post('/logout')->assertRedirect('/');
        $this->assertGuest();
    }

    public function test_address_page_exposes_supported_country_options(): void
    {
        $this->actingAs(User::factory()->create())->get('/account/addresses')->assertInertia(fn ($page) => $page
            ->component('account/addresses')
            ->has('countries')
            ->where('countries.0', 'NL'));
    }

    public function test_account_pages_require_authentication_and_render_for_customers(): void
    {
        $this->get('/account/profile')->assertRedirect(route('login'));
        $user = User::factory()->create();
        $this->actingAs($user)->get('/account/profile')->assertOk();
        $this->get('/account/addresses')->assertOk();
    }

    public function test_customer_can_update_profile_but_cannot_grant_admin_access(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->patch('/account/profile', ['name' => 'New name', 'email' => 'changed@example.com', 'is_admin' => true])->assertSessionHasNoErrors();
        $this->assertSame('New name', $user->fresh()->name);
        $this->assertNull($user->fresh()->email_verified_at);
        $this->assertFalse($user->fresh()->is_admin);
        $this->assertFalse($user->canAccessPanel(Panel::make()));
    }

    public function test_password_changes_require_current_password(): void
    {
        $user = User::factory()->create();
        $payload = ['current_password' => 'wrong', 'password' => 'new-password', 'password_confirmation' => 'new-password'];
        $this->actingAs($user)->put('/account/password', $payload)->assertSessionHasErrors('current_password');
        $this->put('/account/password', [...$payload, 'current_password' => 'password'])->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('new-password', $user->fresh()->password));
    }

    public function test_password_broker_sends_and_consumes_reset_token(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $this->post('/forgot-password', ['email' => $user->email])->assertSessionHas('status');
        Notification::assertSentTo($user, ResetPassword::class);
        $token = Password::createToken($user);
        $payload = ['token' => $token, 'email' => $user->email, 'password' => 'reset-password', 'password_confirmation' => 'reset-password'];
        $this->post('/reset-password', $payload)->assertRedirect(route('login'));
        $this->assertTrue(Hash::check('reset-password', $user->fresh()->password));
        $this->post('/reset-password', $payload)->assertSessionHasErrors('email');
    }

    public function test_unknown_reset_email_has_same_public_response(): void
    {
        $this->post('/forgot-password', ['email' => 'unknown@example.com'])->assertSessionHas('status')->assertSessionHasNoErrors();
    }

    public function test_addresses_are_deduplicated_and_have_one_default(): void
    {
        $user = User::factory()->create();
        $payload = $this->address();
        $this->actingAs($user)->post('/account/addresses', $payload)->assertRedirect()->assertSessionHasNoErrors();
        $this->post('/account/addresses', $payload)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(1, $user->addresses()->count());
        $this->post('/account/addresses', [...$payload, 'line1' => 'Other street', 'is_default' => true])->assertSessionHasNoErrors();
        $this->assertSame(1, $user->addresses()->where('is_default', true)->count());
        $default = $user->addresses()->where('is_default', true)->firstOrFail();
        $this->patch('/account/addresses/'.$default->id, [...$payload, 'line1' => 'Updated street', 'house_number' => '42'])->assertSessionHasNoErrors();
        $this->assertSame('42', $default->fresh()->house_number);
        $this->delete('/account/addresses/'.$default->id)->assertRedirect();
        $this->assertTrue($user->addresses()->firstOrFail()->is_default);
    }

    public function test_other_customers_cannot_change_or_delete_addresses(): void
    {
        $owner = User::factory()->create();
        $address = $owner->addresses()->create($this->address());
        $this->actingAs(User::factory()->create())->patch('/account/addresses/'.$address->id, $this->address())->assertForbidden();
        $this->delete('/account/addresses/'.$address->id)->assertForbidden();
        $this->assertModelExists($address);
    }

    public function test_admin_command_requires_existing_user_and_can_revoke_access(): void
    {
        $user = User::factory()->create();
        $this->artisan('app:grant-administrator', ['email' => 'absent@example.com'])->assertFailed();
        $this->artisan('app:grant-administrator', ['email' => $user->email])->assertSuccessful();
        $this->assertTrue($user->fresh()->canAccessPanel(Panel::make()));
        $this->artisan('app:grant-administrator', ['email' => $user->email, '--revoke' => true])->assertSuccessful();
        $this->assertFalse($user->fresh()->is_admin);
    }

    /** @return array<string, mixed> */
    private function address(): array
    {
        return ['name' => 'Customer', 'email' => 'customer@example.com', 'line1' => 'Main street', 'postal_code' => '1234AB', 'city' => 'Amsterdam', 'country_code' => 'NL'];
    }
}
