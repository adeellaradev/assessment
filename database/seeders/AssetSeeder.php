<?php

namespace Database\Seeders;

use App\Models\Asset;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class AssetSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = User::all();
        $symbols = ['BTC', 'ETH', 'USDT', 'BNB', 'SOL'];

        foreach ($users as $user) {
            foreach ($symbols as $symbol) {
                Asset::create([
                    'user_id' => $user->id,
                    'symbol' => $symbol,
                    'amount' => fake()->randomFloat(8, 0, 100),
                    'locked_amount' => '0.00000000',
                ]);
            }
        }
    }
}
