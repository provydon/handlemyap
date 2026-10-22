<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Models\Vendor> */
class VendorFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->company(),
            'email' => fake()->unique()->companyEmail(),
            'country' => 'US',
            'currency' => 'USD',
        ];
    }

    public function withoutEmail(): static
    {
        return $this->state(['email' => null]);
    }
}
