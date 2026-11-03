<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Safe for production: roles/permissions and branch reference data only.
 * Never creates DEMO users or DEMO tickets.
 */
class ProductionSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            BranchSeeder::class,
        ]);
    }
}
