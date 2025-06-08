<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ItemSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('items')->insert([
            [
                'name' => '腕時計',
                'price' => 15000,
                'description' => 'スタイリッシュなデザインのメンズ腕時計',
                'img_url' => '../../public/img/watch.jpg',
                'user_id' => 1,
                'condition_id' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'HDD',
                'price' => 5000,
                'description' => '高速で信頼性の高いハードディスク',
                'img_url' => '../public/img/hdd.jpg',
                'user_id' => 1,
                'condition_id' => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => '玉ねぎ３束',
                'price' => 300,
                'description' => '新鮮な玉ねぎ3束のセット',
                'img_url' => 'public/img/onion.jpg',
                'user_id' => 1,
                'condition_id' => 3,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => '革靴',
                'price' => 4000,
                'description' => 'クラシックなデザインの革靴',
                'img_url' => 'public/img/shoes.jpg',
                'user_id' => 1,
                'condition_id' => 4,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'ノートPC',
                'price' => 45000,
                'description' => '高性能なノートパソコン',
                'img_url' => 'public/img/laptop.jpg',
                'user_id' => 1,
                'condition_id' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'マイク',
                'price' => 8000,
                'description' => '高音質のレコーディング用マイク',
                'img_url' => 'public/img/microphone.jpg',
                'user_id' => 1,
                'condition_id' => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'ショルダーバッグ',
                'price' => 3500,
                'description' => 'おしゃれなショルダーバッグ',
                'img_url' => 'public/img/bag.jpg',
                'user_id' => 1,
                'condition_id' => 3,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'タンブラー',
                'price' => 500,
                'description' => '使いやすいタンブラー',
                'img_url' => 'public/img/tumbler.jpg',
                'user_id' => 1,
                'condition_id' => 4,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'コーヒーミル',
                'price' => 4000,
                'description' => '手動のコーヒーミル',
                'img_url' => 'public/img/coffee_mill.jpg',
                'user_id' => 1,
                'condition_id' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'メイクセット',
                'price' => 4000,
                'description' => '便利なメイクアップセット',
                'img_url' => 'public/img/makeup_set.jpg',
                'user_id' => 1,
                'condition_id' => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
