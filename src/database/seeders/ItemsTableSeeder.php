<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ItemsTableSeeder extends Seeder
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
                'name' => 'Item 1',
                'description' => 'Description for Item 1',
                'price' => 100,
                'image_url' => '../../storage/app/public/img/dummyItem.png',
                'user_id' => 1,
                'condition_id' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Item 2',
                'description' => 'Description for Item 2',
                'price' => 200,
                'image_url' => '../../storage/app/public/img/dummyItem.png',
                'user_id' => 1,
                'condition_id' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
