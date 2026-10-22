<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\Payout;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Models\Payout> */
class PayoutFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'invoice_id' => Invoice::factory(),
            'amount' => fake()->randomFloat(2, 50, 5000),
            'currency' => 'USD',
            'status' => Payout::STATUS_PENDING,
        ];
    }
}
