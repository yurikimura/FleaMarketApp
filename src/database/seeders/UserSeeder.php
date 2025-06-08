<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserSeeder extends Seeder
{
    /**
     * データベースに対してユーザーデータの追加の実行
     *
     * @return void
     */
    public function run()
    {
        DB::table('users')->insert([
            [
                'name' => Str::random(10),
                'email' => 'test@example.com',
                'password' => 'password123',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'ダミー太郎',
                'email' => 'test2@example.com',
                'password' => 'password123',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
