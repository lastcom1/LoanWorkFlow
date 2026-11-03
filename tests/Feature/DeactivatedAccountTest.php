<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\UserAccountService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class DeactivatedAccountTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_deactivated_user_with_the_right_password_cannot_log_in(): void
    {
        $user = User::factory()->create([
            'email' => 'gone@nnmfb.test',
            'password' => Hash::make('Password@123'),
            'is_active' => false,
        ]);
        $user->assignRole('submitter');

        $this->post(route('login'), [
            'email' => 'gone@nnmfb.test',
            'password' => 'Password@123',
            'remember' => '1',
        ])->assertRedirect()
            ->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertStringContainsString(
            'deactivated',
            strtolower((string) session('errors')->first('email'))
        );
        $this->assertStringContainsString(
            'administrator',
            strtolower((string) session('errors')->first('email'))
        );
    }

    public function test_wrong_password_on_a_deactivated_account_stays_generic(): void
    {
        $user = User::factory()->create([
            'email' => 'gone-wrong@nnmfb.test',
            'password' => Hash::make('Password@123'),
            'is_active' => false,
        ]);
        $user->assignRole('submitter');

        $this->from(route('login'))
            ->post(route('login'), [
                'email' => 'gone-wrong@nnmfb.test',
                'password' => 'not-the-password',
            ])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');

        $this->assertStringNotContainsString(
            'deactivated',
            strtolower((string) session('errors')->first('email'))
        );
        $this->assertGuest();
    }

    public function test_clearing_the_active_checkbox_deactivates_and_blocks_login(): void
    {
        $super = $this->superAdmin();
        $user = User::factory()->create([
            'email' => 'checkbox@nnmfb.test',
            'password' => Hash::make('Password@123'),
            'is_active' => true,
        ]);
        $user->assignRole('submitter');
        $oldToken = $user->remember_token;

        DB::table('sessions')->insert([
            'id' => 'session-checkbox',
            'user_id' => $user->id,
            'payload' => 'test',
            'last_activity' => time(),
        ]);

        $this->actingAs($super)
            ->put(route('admin.users.update', $user), [
                'name' => $user->name,
                'email' => $user->email,
                'staff_id' => $user->staff_id,
                'role' => 'submitter',
            ])
            ->assertRedirect(route('admin.users.index'));

        $user->refresh();
        $this->assertFalse($user->is_active);
        $this->assertNotSame($oldToken, $user->remember_token);
        $this->assertDatabaseMissing('sessions', ['id' => 'session-checkbox']);

        auth()->logout();
        $this->flushSession();

        $this->post(route('login'), [
            'email' => 'checkbox@nnmfb.test',
            'password' => 'Password@123',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertStringContainsString('deactivated', strtolower((string) session('errors')->first('email')));
    }

    public function test_existing_session_is_rejected_on_the_next_request(): void
    {
        $user = User::factory()->create([
            'email' => 'insession@nnmfb.test',
            'password' => Hash::make('Password@123'),
            'is_active' => true,
        ]);
        $user->assignRole('submitter');

        $this->post(route('login'), [
            'email' => 'insession@nnmfb.test',
            'password' => 'Password@123',
        ])->assertRedirect(route('dashboard'));

        app(UserAccountService::class)->deactivate($user->fresh());

        // A later HTTP request resolves the user from the session again.
        $this->app['auth']->forgetGuards();

        $this->get(route('dashboard'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertStringContainsString('deactivated', strtolower((string) session('errors')->first('email')));
    }

    public function test_remember_me_cookie_cannot_restore_a_deactivated_user(): void
    {
        $user = User::factory()->create([
            'email' => 'remember@nnmfb.test',
            'password' => Hash::make('Password@123'),
            'is_active' => true,
        ]);
        $user->assignRole('submitter');

        $response = $this->post(route('login'), [
            'email' => 'remember@nnmfb.test',
            'password' => 'Password@123',
            'remember' => '1',
        ]);
        $response->assertRedirect(route('dashboard'));

        $remember = collect($response->headers->getCookies())
            ->first(fn ($cookie) => str_starts_with($cookie->getName(), 'remember_'));
        $this->assertNotNull($remember);

        app(UserAccountService::class)->deactivate($user->fresh());

        $this->flushSession();
        auth()->logout();

        $this->withCookie($remember->getName(), $remember->getValue())
            ->get(route('dashboard'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_password_reset_is_blocked_for_a_deactivated_account(): void
    {
        $user = User::factory()->create([
            'email' => 'reset-me@nnmfb.test',
            'password' => Hash::make('Password@123'),
            'is_active' => true,
        ]);
        $user->assignRole('submitter');

        $token = Password::broker()->createToken($user);
        app(UserAccountService::class)->deactivate($user->fresh());

        $this->from(route('password.reset', ['token' => $token]))
            ->post(route('password.update'), [
                'token' => $token,
                'email' => $user->email,
                'password' => 'NewSecurePass@123',
                'password_confirmation' => 'NewSecurePass@123',
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('email');

        $this->assertStringContainsString('deactivated', strtolower((string) session('errors')->first('email')));
        $this->assertTrue(Hash::check('Password@123', $user->fresh()->password));
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
    }

    private function superAdmin(): User
    {
        $user = User::factory()->create(['email' => 'super-deactivate@nnmfb.test']);
        $user->assignRole('super_admin');

        return $user;
    }
}
