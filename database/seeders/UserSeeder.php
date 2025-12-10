<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => Hash::make('password'),
            'balance' => '10000.00000000',
        ]);

        User::create([
            'name' => 'Jane Smith',
            'email' => 'jane@example.com',
            'password' => Hash::make('password'),
            'balance' => '15000.00000000',
        ]);

        User::create([
            'name' => 'Bob Johnson',
            'email' => 'bob@example.com',
            'password' => Hash::make('password'),
            'balance' => '20000.00000000',
        ]);

        User::create([
            'name' => 'Alice Williams',
            'email' => 'alice@example.com',
            'password' => Hash::make('password'),
            'balance' => '25000.00000000',
        ]);

        User::create([
            'name' => 'Charlie Brown',
            'email' => 'charlie@example.com',
            'password' => Hash::make('password'),
            'balance' => '30000.00000000',
        ]);
    }
}
