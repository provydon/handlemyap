<?php

namespace Database\Seeders;

use App\Models\AutoApprovalRule;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Demo account for one-click login. No sample invoices — the queue starts
        // empty, so what you see in the demo is what you add.
        $user = User::updateOrCreate(
            ['email' => 'demo@handlemyap.test'],
            ['name' => 'Demo', 'password' => Hash::make('password'), 'handle' => 'demo'],
        );

        AutoApprovalRule::updateOrCreate(
            ['user_id' => $user->id],
            ['enabled' => true, 'max_amount' => 500, 'known_vendors_only' => false, 'block_duplicates' => true],
        );
    }
}
