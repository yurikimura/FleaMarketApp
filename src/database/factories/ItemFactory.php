<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Item;
use App\Models\User;
use App\Models\Condition;

class ItemFactory extends Factory
{
    protected $model = Item::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'name' => $this->faker->words(3, true),
            'price' => $this->faker->numberBetween(100, 50000),
            'description' => $this->faker->paragraph(),
            'img_url' => 'public/img/test-image.jpg',
            'user_id' => User::factory(),
            'condition_id' => Condition::factory(),
        ];
    }
}
