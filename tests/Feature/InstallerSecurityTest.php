<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\InstallerGuard;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class InstallerSecurityTest extends TestCase
{
    use RefreshDatabase;

    private string $lockPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->lockPath = storage_path('app/installed.lock');
        if (File::exists($this->lockPath)) {
            File::delete($this->lockPath);
        }
        config(['installer.enabled' => false, 'installer.setup_secret' => null]);
    }

    protected function tearDown(): void
    {
        if (File::exists($this->lockPath)) {
            File::delete($this->lockPath);
        }
        parent::tearDown();
    }

    public function test_installer_disabled_by_default(): void
    {
        config(['installer.enabled' => false, 'installer.setup_secret' => 'test-secret']);

        $this->get(route('installer.welcome'))->assertForbidden();
    }

    public function test_installer_requires_setup_secret_when_enabled(): void
    {
        config(['installer.enabled' => true, 'installer.setup_secret' => 'correct-secret']);

        $this->get(route('installer.welcome'))->assertForbidden();
        $this->get(route('installer.welcome', ['setup_secret' => 'wrong']))->assertForbidden();
        $this->get(route('installer.welcome', ['setup_secret' => 'correct-secret']))->assertOk();
    }

    public function test_installer_refuses_and_locks_when_users_exist(): void
    {
        config(['installer.enabled' => true, 'installer.setup_secret' => 'correct-secret']);

        User::factory()->create([
            'email' => 'existing@nnmfb.example',
            'password' => Hash::make('ExistingPass@123'),
            'is_demo' => false,
        ]);

        $this->get(route('installer.welcome', ['setup_secret' => 'correct-secret']))
            ->assertForbidden();

        $this->assertTrue(File::exists($this->lockPath));
    }

    public function test_save_admin_never_modifies_existing_user(): void
    {
        config(['installer.enabled' => true, 'installer.setup_secret' => 'correct-secret']);

        $existing = User::factory()->create([
            'email' => 'admin@nnmfb.example',
            'name' => 'Original Admin',
            'password' => Hash::make('OriginalPass@123'),
            'is_demo' => false,
        ]);
        $existing->assignRole('admin');

        $this->withSession(['installer_setup_secret' => 'correct-secret'])
            ->post(route('installer.admin.save'), [
                'name' => 'Hacker',
                'email' => 'admin@nnmfb.example',
                'password' => 'HackedPass@123',
                'password_confirmation' => 'HackedPass@123',
            ])
            ->assertForbidden();

        $existing->refresh();
        $this->assertSame('Original Admin', $existing->name);
        $this->assertTrue(Hash::check('OriginalPass@123', $existing->password));
    }

    public function test_save_admin_creates_fresh_admin_and_locks_installer(): void
    {
        config(['installer.enabled' => true, 'installer.setup_secret' => 'correct-secret']);

        $this->withSession(['installer_setup_secret' => 'correct-secret'])
            ->post(route('installer.admin.save'), [
                'name' => 'Fresh Admin',
                'email' => 'fresh-admin@nnmfb.example',
                'password' => 'FreshAdmin@12345',
                'password_confirmation' => 'FreshAdmin@12345',
            ])
            ->assertRedirect(route('installer.complete'));

        $this->assertDatabaseHas('users', [
            'email' => 'fresh-admin@nnmfb.example',
            'name' => 'Fresh Admin',
            'is_demo' => 0,
        ]);
        $this->assertTrue(File::exists($this->lockPath));

        $this->get(route('installer.welcome', ['setup_secret' => 'correct-secret']))
            ->assertForbidden();
    }

    public function test_artisan_installer_lock_command_writes_lock(): void
    {
        $this->artisan('installer:lock', ['--email' => 'ops@nnmfb.example'])
            ->assertSuccessful();

        $this->assertTrue(app(InstallerGuard::class)->isLocked());
    }
}
