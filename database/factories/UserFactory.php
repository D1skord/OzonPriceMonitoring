<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    public function definition(): array
    {
        $firstName = fake()->firstName();
        $lastName = fake()->lastName();

        return [
            'vk_user_id' => fake()->unique()->numberBetween(1000, 999999999),
            'vk_peer_id' => fake()->unique()->numberBetween(1000, 999999999),
            'name' => $firstName.' '.$lastName,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'last_seen_at' => now(),
        ];
    }
}
