<?php

namespace App\Database;

use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

/**
 * Rename the original full-access "admin" role to "super_admin" (same row,
 * so existing assignments stay) and add a limited "admin" role beneath it.
 *
 * Idempotent. Does not delete users or ticket data.
 */
class PromoteAdminRole
{
    public function up(): void
    {
        if (! Schema::hasTable('roles')) {
            return;
        }

        $guard = 'web';
        $roles = $this->rolesTable();

        $super = DB::table($roles)->where('name', 'super_admin')->where('guard_name', $guard)->first();
        $legacy = DB::table($roles)->where('name', 'admin')->where('guard_name', $guard)->first();

        // Only the original full-access role exists. Rename that row in place.
        if (! $super && $legacy) {
            DB::table($roles)->where('id', $legacy->id)->update([
                'name' => 'super_admin',
                'updated_at' => now(),
            ]);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        (new RoleSeeder)->run();
    }

    public function down(): void
    {
        if (! Schema::hasTable('roles')) {
            return;
        }

        $guard = 'web';
        $roles = $this->rolesTable();
        $admin = DB::table($roles)->where('name', 'admin')->where('guard_name', $guard)->first();
        $super = DB::table($roles)->where('name', 'super_admin')->where('guard_name', $guard)->first();

        // If staff already hold the limited Admin role, leave both rows.
        // Renaming super_admin back onto "admin" would collide and drop access.
        if ($admin && $super && Schema::hasTable('model_has_roles')) {
            $assigned = DB::table('model_has_roles')->where('role_id', $admin->id)->exists();

            if (! $assigned) {
                if (Schema::hasTable('role_has_permissions')) {
                    DB::table('role_has_permissions')->where('role_id', $admin->id)->delete();
                }

                DB::table($roles)->where('id', $admin->id)->delete();
                DB::table($roles)->where('id', $super->id)->update([
                    'name' => 'admin',
                    'updated_at' => now(),
                ]);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function rolesTable(): string
    {
        return (string) config('permission.table_names.roles', 'roles');
    }
}
