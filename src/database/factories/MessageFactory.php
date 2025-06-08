<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Message;
use App\Models\User;
use App\Models\Item;

class MessageFactory extends Factory
{
    protected $model = Message::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'sender_id' => User::factory(),
            'receiver_id' => User::factory(),
            'item_id' => Item::factory(),
            'message' => $this->faker->paragraph(),
            'is_read' => $this->faker->boolean(30), // 30%の確率で既読
        ];
    }

    /**
     * 未読メッセージの状態
     */
    public function unread()
    {
        return $this->state(function (array $attributes) {
            return [
                'is_read' => false,
            ];
        });
    }

    /**
     * 既読メッセージの状態
     */
    public function read()
    {
        return $this->state(function (array $attributes) {
            return [
                'is_read' => true,
            ];
        });
    }
}
