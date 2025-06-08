<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    use HasFactory;

    protected $fillable = [
        'sender_id',
        'receiver_id',
        'item_id',
        'message',
        'is_read',
        'is_edited'
    ];

    protected $casts = [
        'is_read' => 'boolean',
        'is_edited' => 'boolean',
    ];

    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function receiver()
    {
        return $this->belongsTo(User::class, 'receiver_id');
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    /**
     * 未読メッセージかどうかを判定
     */
    public function isUnread()
    {
        return !$this->is_read;
    }

    /**
     * メッセージを既読にする
     */
    public function markAsRead()
    {
        $this->update(['is_read' => true]);
    }
}
