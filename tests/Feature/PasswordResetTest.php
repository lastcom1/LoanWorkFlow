<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_forgot_password_link_is_on_login_page(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Forgot password?')
            ->assertSee(route('password.request'), false);
    }

    public function test_demo_user_can_request_password_reset(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email' => 'submitter@nnmfb.demo',
            'is_demo' => true,
            'is_active' => true,
        ]);
        $user->assignRole('submitter');

        $this->post(route('password.email'), ['email' => $user->email])
            ->assertRedirect()
            ->assertSessionHas('status');

        Notification::assertSentTo($user, ResetPasswordNotification::class);
    }

    public function test_user_can_reset_password_with_valid_token(): void
    {
        $user = User::factory()->create([
            'email' => 'admin@nnmfb.demo',
            'is_demo' => true,
            'is_active' => true,
        ]);
        $user->assignRole('admin');

        $token = Password::broker()->createToken($user);

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'NewSecurePass@123',
            'password_confirmation' => 'NewSecurePass@123',
        ])->assertRedirect(route('login'));

        $this->assertTrue(auth()->attempt([
            'email' => $user->email,
            'password' => 'NewSecurePass@123',
        ]));
    }

    public function test_forgot_password_always_returns_generic_status(): void
    {
        Notification::fake();

        // Unknown email
        $this->from(route('password.request'))
            ->post(route('password.email'), ['email' => 'nobody@nnmfb.example'])
            ->assertRedirect()
            ->assertSessionHas('status')
            ->assertSessionHasNoErrors();

        // Inactive account — same generic status (no existence / eligibility leak)
        $inactive = User::factory()->create([
            'email' => 'inactive@nnmfb.demo',
            'is_active' => false,
        ]);

        $this->from(route('password.request'))
            ->post(route('password.email'), ['email' => $inactive->email])
            ->assertRedirect()
            ->assertSessionHas('status')
            ->assertSessionHasNoErrors();

        Notification::assertNothingSent();
    }
}
