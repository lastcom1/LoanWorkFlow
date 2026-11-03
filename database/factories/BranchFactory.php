<?php

namespace Database\Factories;

use App\Models\Branch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Branch>
 */
class BranchFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->bothify('BR-###')),
            'name' => fake()->company().' Branch',
            'city' => fake()->city(),
            'state' => fake()->state(),
            'is_active' => true,
        ];
    }
}
