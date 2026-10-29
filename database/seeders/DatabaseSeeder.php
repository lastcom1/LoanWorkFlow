<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Always safe reference data.
        $this->call([
            ProductionSeeder::class,
        ]);

        // DEMO users/tickets only in local/testing — never production.
        if ($this->shouldSeedDemoData()) {
            $this->call([
                DefaultAdminSeeder::class,
                DemoTicketSeeder::class,
            ]);
        }
    }

    private function shouldSeedDemoData(): bool
    {
        if (app()->environment('production')) {
            return false;
        }

        // Explicit opt-in for non-production (local/staging/testing).
        return filter_var(env('SEED_DEMO_DATA', true), FILTER_VALIDATE_BOOLEAN);
    }
}
