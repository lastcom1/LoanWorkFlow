<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Local/demo only. Never run on production hosts.
 *
 *   php artisan db:seed --class=DemoDatabaseSeeder
 */
class DemoDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command?->error('Refusing to seed DEMO data while APP_ENV=production.');

            return;
        }

        $this->call([
            ProductionSeeder::class,
            DefaultAdminSeeder::class,
            DemoTicketSeeder::class,
        ]);
    }
}
