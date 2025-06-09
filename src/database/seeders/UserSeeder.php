<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        \DB::table('users')->insert([
            [
                'name' => '山田太郎',
                'email' => 'test@example.com',
                'password' => Hash::make('password123'),
                'created_at' => now()->subMonths(3),
                'updated_at' => now()->subMonths(3),
            ],
            [
                'name' => '佐藤花子',
                'email' => 'test2@example.com',
                'password' => Hash::make('password123'),
                'created_at' => now()->subMonths(3),
                'updated_at' => now()->subMonths(3),
            ],
            [
                'name' => '鈴木一郎',
                'email' => 'test3@example.com',
                'password' => Hash::make('password123'),
                'created_at' => now()->subMonths(3),
                'updated_at' => now()->subMonths(3),
            ],
            [
                'name' => '田中美咲',
                'email' => 'test4@example.com',
                'password' => Hash::make('password123'),
                'created_at' => now()->subMonths(3),
                'updated_at' => now()->subMonths(3),
            ],
        ]);
    }
}