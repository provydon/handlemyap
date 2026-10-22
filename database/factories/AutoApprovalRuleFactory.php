<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Models\AutoApprovalRule> */
class AutoApprovalRuleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'enabled' => true,
            'max_amount' => 1000,
            'known_vendors_only' => false,
            'block_duplicates' => true,
        ];
    }
}
