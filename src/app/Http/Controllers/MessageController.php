<?php

namespace App\Http\Controllers;

use App\Models\Message;
use Illuminate\Http\Request;

class MessageController extends Controller
{
    /**
     * メッセージを削除する
     *
     * @param  \App\Models\Message  $message
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy(Message $message)
    {
        // メッセージの送信者かどうかを確認
        if ($message->sender_id !== auth()->id()) {
            abort(403);
        }

        $itemId = $message->item_id;
        $message->delete();

        return redirect()->route('chat.show', $itemId);
    }
}
