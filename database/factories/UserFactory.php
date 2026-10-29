<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'staff_id' => 'STAFF-'.fake()->unique()->numerify('####'),
            'is_active' => true,
            'is_demo' => false,
            'must_change_password' => false,
            'password_changed_at' => now(),
        ];
    }

    public function mustChangePassword(): static
    {
        return $this->state(fn () => [
            'must_change_password' => true,
            'password_changed_at' => null,
        ]);
    }
}
