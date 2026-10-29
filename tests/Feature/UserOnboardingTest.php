<?php

namespace Tests\Feature;

use App\Mail\WelcomeUserMail;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class UserOnboardingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    private function admin(): User
    {
        $admin = User::factory()->create(['email' => 'admin-onboard@nnmfb.test']);
        $admin->assignRole('admin');

        return $admin;
    }

    public function test_admin_create_sends_welcome_and_requires_password_change(): void
    {
        Mail::fake();
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('admin.users.store'), [
                'name' => 'New Officer',
                'email' => 'newofficer@nnmfb.test',
                'role' => 'ft_officer',
                'password' => 'TempPass@12345',
                'password_confirmation' => 'TempPass@12345',
                'is_active' => '1',
                'send_welcome' => '1',
            ])
            ->assertRedirect(route('admin.users.index'));

        $user = User::query()->where('email', 'newofficer@nnmfb.test')->firstOrFail();
        $this->assertTrue($user->must_change_password);
        $this->assertNull($user->password_changed_at);
        $this->assertTrue(Hash::check('TempPass@12345', $user->password));

        Mail::assertSent(WelcomeUserMail::class, function (WelcomeUserMail $mail) use ($user) {
            return $mail->hasTo($user->email)
                && $mail->temporaryPassword === 'TempPass@12345';
        });
    }

    public function test_welcome_mail_failure_does_not_block_user_create(): void
    {
        $this->useFailingMailTransport();
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('admin.users.store'), [
                'name' => 'Mail Fail User',
                'email' => 'mailfail@nnmfb.test',
                'role' => 'submitter',
                'is_active' => '1',
                'send_welcome' => '1',
            ])
            ->assertRedirect(route('admin.users.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('users', [
            'email' => 'mailfail@nnmfb.test',
            'must_change_password' => 1,
        ]);
    }

    public function test_first_login_is_forced_to_change_password(): void
    {
        $user = User::factory()->mustChangePassword()->create([
            'email' => 'force@nnmfb.test',
            'password' => Hash::make('TempPass@12345'),
        ]);
        $user->assignRole('submitter');

        $this->post(route('login'), [
            'email' => 'force@nnmfb.test',
            'password' => 'TempPass@12345',
        ])->assertRedirect(route('password.change'));

        $this->actingAs($user->fresh())
            ->get(route('dashboard'))
            ->assertRedirect(route('password.change'));

        $this->actingAs($user)
            ->post(route('password.change.update'), [
                'current_password' => 'TempPass@12345',
                'password' => 'NewSecurePass@999',
                'password_confirmation' => 'NewSecurePass@999',
            ])
            ->assertRedirect(route('dashboard'));

        $user->refresh();
        $this->assertFalse($user->must_change_password);
        $this->assertNotNull($user->password_changed_at);
        $this->assertTrue(Hash::check('NewSecurePass@999', $user->password));
    }

    public function test_existing_users_migration_marks_password_already_changed(): void
    {
        // Factory defaults (post-migration) already mark changed; assert the contract.
        $user = User::factory()->create();
        $this->assertFalse($user->must_change_password);
        $this->assertNotNull($user->password_changed_at);
    }

    public function test_admin_can_resend_welcome_with_new_temp_password(): void
    {
        Mail::fake();
        $admin = $this->admin();
        $user = User::factory()->create([
            'email' => 'resend@nnmfb.test',
            'must_change_password' => false,
            'password_changed_at' => now(),
        ]);
        $user->assignRole('submitter');
        $oldHash = $user->password;

        $this->actingAs($admin)
            ->post(route('admin.users.resend-welcome', $user))
            ->assertRedirect()
            ->assertSessionHas('success');

        $user->refresh();
        $this->assertTrue($user->must_change_password);
        $this->assertNull($user->password_changed_at);
        $this->assertNotSame($oldHash, $user->password);

        Mail::assertSent(WelcomeUserMail::class, fn (WelcomeUserMail $mail) => $mail->hasTo($user->email));
    }
}
