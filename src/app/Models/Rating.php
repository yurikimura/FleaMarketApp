<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Rating extends Model
{
    use HasFactory;

    protected $fillable = [
        'rater_id',
        'rated_user_id',
        'item_id',
        'rating',
        'comment'
    ];

    /**
     * 評価を行ったユーザー
     */
    public function rater()
    {
        return $this->belongsTo(User::class, 'rater_id');
    }

    /**
     * 評価されたユーザー
     */
    public function ratedUser()
    {
        return $this->belongsTo(User::class, 'rated_user_id');
    }

    /**
     * 評価対象の商品
     */
    public function item()
    {
        return $this->belongsTo(Item::class);
    }
}
