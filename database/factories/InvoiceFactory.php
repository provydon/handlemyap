<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Models\Invoice> */
class InvoiceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'source' => 'upload',
            'status' => Invoice::STATUS_RECEIVED,
            'vendor_name' => fake()->company(),
            'invoice_number' => 'INV-'.fake()->unique()->numberBetween(1000, 9999),
            'amount' => fake()->randomFloat(2, 50, 5000),
            'currency' => 'USD',
            'due_date' => now()->addDays(7),
            'confidence' => 95,
        ];
    }
}
