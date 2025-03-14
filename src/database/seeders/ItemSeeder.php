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
            'name' => 'dummydata1',
            'price' => 3000,
            'description' => 'dummy dummy dummy',
            'img_url' => 'public/img/dummyItem.png',
            'user_id' => 1,
            'condition_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
