<?php

namespace Database\Seeders;

use App\Models\Branch;
use Illuminate\Database\Seeder;

class BranchSeeder extends Seeder
{
    public function run(): void
    {
        $branches = [
            ['code' => 'HQ-ABJ', 'name' => 'Head Office — Abuja', 'city' => 'Abuja', 'state' => 'FCT'],
            ['code' => 'BR-LAG', 'name' => 'Lagos Island Branch', 'city' => 'Lagos', 'state' => 'Lagos'],
            ['code' => 'BR-PHC', 'name' => 'Port Harcourt Branch', 'city' => 'Port Harcourt', 'state' => 'Rivers'],
            ['code' => 'BR-KAD', 'name' => 'Kaduna Branch', 'city' => 'Kaduna', 'state' => 'Kaduna'],
            ['code' => 'BR-ENU', 'name' => 'Enugu Branch', 'city' => 'Enugu', 'state' => 'Enugu'],
        ];

        foreach ($branches as $branch) {
            Branch::query()->updateOrCreate(
                ['code' => $branch['code']],
                $branch + ['is_active' => true]
            );
        }
    }
}
