<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            'tickets.view', 'tickets.create', 'tickets.claim', 'tickets.process',
            'tickets.reject', 'tickets.reassign', 'users.manage', 'branches.manage',
            'settings.manage', 'audit.view', 'audit.export', 'dashboard.executive',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission);
        }

        $map = [
            'admin' => $permissions,
            'md_ceo' => ['tickets.view', 'tickets.reassign', 'audit.view', 'dashboard.executive'],
            'supervisor' => ['tickets.view', 'tickets.reassign', 'audit.view'],
            'ft_officer' => ['tickets.view', 'tickets.claim', 'tickets.process', 'tickets.reject'],
            'submitter' => ['tickets.view', 'tickets.create'],
            'auditor' => ['tickets.view', 'audit.view', 'audit.export'],
        ];

        foreach ($map as $roleName => $perms) {
            $role = Role::findOrCreate($roleName);
            $role->syncPermissions($perms);
        }
    }
}
