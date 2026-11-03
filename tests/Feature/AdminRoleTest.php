<?php

namespace Tests\Feature;

use App\Database\PromoteAdminRole;
use App\Mail\WelcomeUserMail;
use App\Models\Branch;
use App\Models\TransferTicket;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AdminRoleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_admin_can_onboard_activate_and_list_users_but_not_promote(): void
    {
        Mail::fake();
        $admin = $this->admin();
        $submitter = User::factory()->create(['email' => 'staff@nnmfb.test', 'is_active' => true]);
        $submitter->assignRole('submitter');

        $this->actingAs($admin)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee($submitter->email)
            ->assertSee('Deactivate');

        $this->actingAs($admin)
            ->get(route('admin.users.create'))
            ->assertOk()
            ->assertSee('Fund Transfer Officer')
            ->assertDontSee('Super Admin');

        $this->actingAs($admin)
            ->get(route('admin.users.edit', $submitter))
            ->assertOk()
            ->assertSee('Active');

        $this->actingAs($admin)
            ->post(route('admin.users.store'), [
                'name' => 'New Submitter',
                'email' => 'new-staff@nnmfb.test',
                'role' => 'submitter',
                'is_active' => '1',
                'send_welcome' => '1',
            ])
            ->assertRedirect(route('admin.users.index'));

        $created = User::query()->where('email', 'new-staff@nnmfb.test')->firstOrFail();
        $this->assertTrue($created->hasRole('submitter'));
        $this->assertTrue($created->must_change_password);
        Mail::assertSent(WelcomeUserMail::class, fn (WelcomeUserMail $mail) => $mail->hasTo($created->email));

        $this->actingAs($admin)
            ->post(route('admin.users.store'), [
                'name' => 'Nope Admin',
                'email' => 'nope-admin@nnmfb.test',
                'role' => 'admin',
                'is_active' => '1',
                'send_welcome' => '0',
            ])
            ->assertForbidden();

        $this->actingAs($admin)
            ->post(route('admin.users.store'), [
                'name' => 'Nope Super',
                'email' => 'nope-super@nnmfb.test',
                'role' => 'super_admin',
                'is_active' => '1',
                'send_welcome' => '0',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'nope-admin@nnmfb.test']);
        $this->assertDatabaseMissing('users', ['email' => 'nope-super@nnmfb.test']);

        $this->actingAs($admin)
            ->put(route('admin.users.update', $submitter), $this->payload($submitter, 'admin'))
            ->assertForbidden();
        $this->assertTrue($submitter->fresh()->hasRole('submitter'));

        $this->actingAs($admin)
            ->post(route('admin.users.deactivate', $submitter))
            ->assertRedirect();
        $this->assertFalse($submitter->fresh()->is_active);

        $this->actingAs($admin)
            ->post(route('admin.users.activate', $submitter))
            ->assertRedirect();
        $this->assertTrue($submitter->fresh()->is_active);
    }

    public function test_admin_cannot_touch_super_admins_or_deactivate_themselves(): void
    {
        $admin = $this->admin();
        $super = $this->superAdmin();

        $this->actingAs($admin)->get(route('admin.users.edit', $super))->assertForbidden();
        $this->actingAs($admin)
            ->put(route('admin.users.update', $super), $this->payload($super, 'submitter'))
            ->assertForbidden();
        $this->actingAs($admin)->post(route('admin.users.deactivate', $super))->assertForbidden();
        $this->actingAs($admin)->post(route('admin.users.resend-welcome', $super))->assertForbidden();
        $this->assertTrue($super->fresh()->is_active);
        $this->assertTrue($super->fresh()->isSuperAdmin());

        $this->actingAs($admin)->post(route('admin.users.deactivate', $admin))->assertForbidden();
        $this->assertTrue($admin->fresh()->is_active);

        $this->actingAs($admin)
            ->put(route('admin.users.update', $admin), $this->payload($admin, 'admin', ['is_active' => '0']))
            ->assertSessionHasErrors('is_active');
        $this->assertTrue($admin->fresh()->is_active);
        $this->assertTrue($admin->fresh()->hasRole('admin'));
    }

    public function test_admin_views_tickets_but_does_not_operate_them(): void
    {
        $admin = $this->admin();
        $branch = Branch::factory()->create();
        $submitter = User::factory()->create(['branch_id' => $branch->id]);
        $submitter->assignRole('submitter');
        $ticket = TransferTicket::factory()->create([
            'submitter_id' => $submitter->id,
            'branch_id' => $branch->id,
        ]);

        $this->actingAs($admin)->get(route('dashboard'))->assertOk()->assertSee('Manage users');
        $this->actingAs($admin)->get(route('tickets.index'))->assertOk();
        $this->actingAs($admin)->get(route('tickets.show', $ticket))->assertOk();
        $this->actingAs($admin)->get(route('tickets.create'))->assertForbidden();
        $this->actingAs($admin)->post(route('tickets.claim', $ticket))->assertForbidden();
        $this->actingAs($admin)->post(route('tickets.comment', $ticket), [
            'body' => 'Admin should not write this',
        ])->assertForbidden();
        $this->actingAs($admin)->get(route('admin.branches.index'))->assertForbidden();
        $this->actingAs($admin)->get(route('admin.settings.edit'))->assertForbidden();
        $this->actingAs($admin)->get(route('auditor.audit-logs'))->assertForbidden();

        $super = $this->superAdmin();
        $this->actingAs($super)->get(route('admin.branches.index'))->assertOk();
        $this->actingAs($super)->get(route('admin.settings.edit'))->assertOk();
        $this->actingAs($super)->get(route('tickets.create'))->assertOk();
    }

    public function test_super_admin_can_assign_the_admin_role(): void
    {
        Mail::fake();
        $super = $this->superAdmin();

        $this->actingAs($super)
            ->post(route('admin.users.store'), [
                'name' => 'Portal Admin',
                'email' => 'portal-admin@nnmfb.test',
                'role' => 'admin',
                'is_active' => '1',
                'send_welcome' => '1',
            ])
            ->assertRedirect(route('admin.users.index'));

        $created = User::query()->where('email', 'portal-admin@nnmfb.test')->firstOrFail();
        $this->assertTrue($created->hasRole('admin'));
        $this->assertFalse($created->isSuperAdmin());
        $this->assertTrue($created->must_change_password);
        Mail::assertSent(WelcomeUserMail::class);
    }

    public function test_role_migration_renames_legacy_admin_without_dropping_assignments(): void
    {
        $legacy = Role::findByName('super_admin');
        $limited = Role::findByName('admin');
        $legacyId = $legacy->id;

        DB::table('role_has_permissions')->where('role_id', $limited->id)->delete();
        DB::table('model_has_roles')->where('role_id', $limited->id)->delete();
        $limited->delete();

        DB::table('roles')->where('id', $legacyId)->update(['name' => 'admin']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $user = User::factory()->create(['email' => 'legacy-admin@nnmfb.test']);
        $user->assignRole('admin');
        $this->assertSame($legacyId, $user->roles()->first()->id);

        $migration = new PromoteAdminRole;
        $migration->up();
        $migration->up();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $user->unsetRelation('roles');

        $this->assertSame(1, DB::table('roles')->where('name', 'super_admin')->count());
        $this->assertSame(1, DB::table('roles')->where('name', 'admin')->count());
        $this->assertDatabaseHas('roles', ['id' => $legacyId, 'name' => 'super_admin']);
        $this->assertTrue($user->fresh()->hasRole('super_admin'));
        $this->assertFalse($user->fresh()->hasRole('admin'));
        $this->assertTrue($user->fresh()->isSuperAdmin());
    }

    private function admin(): User
    {
        $user = User::factory()->create(['email' => 'admin-role@nnmfb.test']);
        $user->assignRole('admin');

        return $user;
    }

    private function superAdmin(): User
    {
        $user = User::factory()->create(['email' => 'super-role@nnmfb.test']);
        $user->assignRole('super_admin');

        return $user;
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function payload(User $user, string $role, array $extra = []): array
    {
        return array_merge([
            'name' => $user->name,
            'email' => $user->email,
            'staff_id' => $user->staff_id,
            'role' => $role,
            'is_active' => '1',
        ], $extra);
    }
}
