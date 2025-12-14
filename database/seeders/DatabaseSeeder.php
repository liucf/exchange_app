<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        $sellUser = User::factory()->create([
            'name' => 'Sell User',
            'email' => 'sell@example.com',
            'balance' => 0,
        ]);
        $sellUser->assets()->create([
            'symbol' => 'BTC',
            'amount' => 5,
            'locked_amount' => 0,
        ]);
        $sellUser->assets()->create([
            'symbol' => 'ETH',
            'amount' => 20,
            'locked_amount' => 0,
        ]);

        User::factory()->create([
            'name' => 'Buy User',
            'email' => 'buy@example.com',
            'balance' => 100000,
        ]);
    }
}
