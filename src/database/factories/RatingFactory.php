<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Rating;
use App\Models\User;
use App\Models\Item;

class RatingFactory extends Factory
{
    protected $model = Rating::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'rater_id' => User::factory(),
            'rated_user_id' => User::factory(),
            'item_id' => Item::factory(),
            'rating' => $this->faker->numberBetween(1, 5),
            'comment' => $this->faker->sentence(),
        ];
    }
}
