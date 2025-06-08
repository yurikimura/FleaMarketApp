<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    public function profile()
    {
        return $this->hasOne('App\Models\Profile');
    }

    public function likes()
    {
        return $this->hasMany('App\Models\Like');
    }

    public function comments()
    {
        return $this->hasMany('App\Models\Comment');
    }

    public function items()
    {
        return $this->hasMany('App\Models\Item');
    }

    public function sentMessages()
    {
        return $this->hasMany(Message::class, 'sender_id');
    }

    public function receivedMessages()
    {
        return $this->hasMany(Message::class, 'receiver_id');
    }

    /**
     * 未読メッセージ数を取得
     */
    public function getUnreadMessageCount()
    {
        return $this->receivedMessages()->where('is_read', false)->count();
    }

    /**
     * 特定の商品に関する未読メッセージ数を取得
     */
    public function getUnreadMessageCountForItem($itemId)
    {
        return $this->receivedMessages()
            ->where('item_id', $itemId)
            ->where('is_read', false)
            ->count();
    }

    /**
     * 取引中の商品に関する未読メッセージ数を取得
     */
    public function getUnreadMessageCountForPurchasedItems()
    {
        $purchasedItemIds = SoldItem::where('user_id', $this->id)->pluck('item_id');

        return $this->receivedMessages()
            ->whereIn('item_id', $purchasedItemIds)
            ->where('is_read', false)
            ->count();
    }

    /**
     * このユーザーが行った評価
     */
    public function givenRatings()
    {
        return $this->hasMany(Rating::class, 'rater_id');
    }

    /**
     * このユーザーが受けた評価
     */
    public function receivedRatings()
    {
        return $this->hasMany(Rating::class, 'rated_user_id');
    }

    /**
     * 平均評価を取得
     */
    public function getAverageRating()
    {
        return $this->receivedRatings()->avg('rating');
    }

    /**
     * 評価数を取得
     */
    public function getRatingCount()
    {
        return $this->receivedRatings()->count();
    }
}
