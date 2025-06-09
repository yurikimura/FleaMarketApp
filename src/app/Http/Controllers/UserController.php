<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Profile;
use App\Models\User;
use App\Models\Item;
use App\Models\SoldItem;
use App\Models\Message;
use App\Http\Requests\ProfileRequest;
use Illuminate\Support\Facades\Storage;
use App\Models\Rating;
use App\Mail\TransactionCompletedMail;
use Illuminate\Support\Facades\Mail;

class UserController extends Controller
{
    public function profile(){

        $profile = Profile::where('user_id', Auth::id())->first();

        return view('profile',compact('profile'));
    }

    public function updateProfile(ProfileRequest $request){

        $img = $request->file('img_url');
        if (isset($img)){
            $img_url = Storage::disk('local')->put('public/img', $img);
        }else{
            $img_url = '';
        }

        $profile = Profile::where('user_id', Auth::id())->first();
        $isNewProfile = !$profile; // 新規プロフィールかどうかを判定

        if ($profile){
            $profile->update([
                'user_id' => Auth::id(),
                'img_url' => $img_url,
                'postcode' => $request->postcode,
                'address' => $request->address,
                'building' => $request->building
            ]);
        }else{
            Profile::create([
                'user_id' => Auth::id(),
                'img_url' => $img_url,
                'postcode' => $request->postcode,
                'address' => $request->address,
                'building' => $request->building
            ]);
        }

        User::find(Auth::id())->update([
            'name' => $request->name
        ]);

        // 新規プロフィール作成の場合は、完了メッセージと共にホームページへ
        if ($isNewProfile) {
            return redirect('/')->with('success', 'プロフィール設定が完了しました！商品の購入が可能になりました。');
        }

        // 既存プロフィール更新の場合は、従来通りホームページへ
        return redirect('/');
    }

    public function mypage(Request $request){
        $user = User::find(Auth::id());

        if ($request->page == 'buy'){
            // 購入した商品：取引完了済みの商品のみ表示
            $items = SoldItem::where('user_id', $user->id)
                           ->where('is_completed', true) // 取引完了済みのみ
                           ->get()->map(function ($sold_item) {
                return $sold_item->item;
            });
        } elseif ($request->page == 'trading') {
            // 取引中の商品：購入済みで取引完了していない商品

            // 購入者として取引中の商品
            $purchasedItems = SoldItem::where('user_id', $user->id)
                            ->where('is_completed', false)
                            ->with(['item.user'])
                            ->get()
                            ->map(function ($sold_item) use ($user) {
                                $item = $sold_item->item;
                                // 未読メッセージ数を取得
                                $item->unread_count = Message::where('item_id', $item->id)
                                                            ->where('receiver_id', $user->id)
                                                            ->where('is_read', false)
                                                            ->count();
                                $item->transaction_type = 'purchased'; // 購入者として
                                return $item;
                            });

            // 出品者として取引中の商品（自分が出品した商品で購入済みかつ取引完了していない）
            $soldItems = Item::where('user_id', $user->id)
                           ->whereHas('soldItem', function($query) {
                               $query->where('is_completed', false);
                           })
                           ->with(['soldItem.user'])
                           ->get()
                           ->map(function ($item) use ($user) {
                               // 未読メッセージ数を取得
                               $item->unread_count = Message::where('item_id', $item->id)
                                                           ->where('receiver_id', $user->id)
                                                           ->where('is_read', false)
                                                           ->count();
                               $item->transaction_type = 'sold'; // 出品者として
                               return $item;
                           });

            // 購入者と出品者の取引中商品を結合
            $items = $purchasedItems->concat($soldItems);
        } else {
            $items = Item::where('user_id', $user->id)->get();
        }

        // 取引中の商品数を取得
        $tradingItemsCount = SoldItem::where('user_id', $user->id)
                                   ->where('is_completed', false)
                                   ->count();

        // 出品者として取引中の商品数も追加
        $soldTradingItemsCount = Item::where('user_id', $user->id)
                                   ->whereHas('soldItem', function($query) {
                                       $query->where('is_completed', false);
                                   })
                                   ->count();

        $tradingItemsCount += $soldTradingItemsCount;

        // 未読メッセージ数を取得
        $unreadMessageCount = $user->getUnreadMessageCount();
        $unreadMessageCountForPurchasedItems = $user->getUnreadMessageCountForPurchasedItems();

        return view('mypage', compact('user', 'items', 'unreadMessageCount', 'unreadMessageCountForPurchasedItems', 'tradingItemsCount'));
    }

    /**
     * 取引チャット画面を表示
     */
    public function chat($itemId)
    {
        $user = User::find(Auth::id());
        $item = Item::findOrFail($itemId);

        // 購入者かどうかを確認
        $soldItem = SoldItem::where('user_id', $user->id)
                           ->where('item_id', $itemId)
                           ->first();

        if (!$soldItem && $item->user_id !== $user->id) {
            abort(403, 'この取引にアクセスする権限がありません。');
        }

        // メッセージを取得（送信者・受信者どちらでも表示）
        $messages = Message::where('item_id', $itemId)
                          ->where(function($query) use ($user) {
                              $query->where('sender_id', $user->id)
                                    ->orWhere('receiver_id', $user->id);
                          })
                          ->orderBy('created_at', 'asc')
                          ->get();

        // 受信したメッセージを既読にする
        Message::where('item_id', $itemId)
               ->where('receiver_id', $user->id)
               ->where('is_read', false)
               ->update(['is_read' => true]);

        // サイドバー用：ユーザーの他の取引中商品を取得
        $otherTransactions = $this->getUserTransactions($user, $itemId);

        // 取引完了状態を確認
        $isTransactionCompleted = $soldItem ? $soldItem->is_completed : false;

        // 評価状況を確認
        $hasRated = false;
        $canRate = false;

        if ($isTransactionCompleted) {
            // 取引相手を特定
            $partnerId = null;
            if ($soldItem) {
                // 購入者の場合、出品者を評価対象とする
                $partnerId = $item->user_id;
            } else {
                // 出品者の場合、購入者を評価対象とする
                $partnerId = SoldItem::where('item_id', $itemId)->first()->user_id;
            }

            // 既に評価済みかチェック
            $hasRated = Rating::where('rater_id', $user->id)
                             ->where('rated_user_id', $partnerId)
                             ->where('item_id', $itemId)
                             ->exists();

            $canRate = !$hasRated;
        }

        return view('chat', compact('item', 'messages', 'user', 'otherTransactions', 'isTransactionCompleted', 'canRate', 'hasRated'));
    }

    /**
     * ユーザーの取引中商品を取得（現在の商品を除く）
     */
    private function getUserTransactions($user, $excludeItemId = null)
    {
        // 購入した商品
        $purchasedItems = SoldItem::where('user_id', $user->id)
                                 ->when($excludeItemId, function($query, $excludeItemId) {
                                     return $query->where('item_id', '!=', $excludeItemId);
                                 })
                                 ->with(['item' => function($query) {
                                     $query->with('user');
                                 }])
                                 ->get()
                                 ->map(function ($soldItem) use ($user) {
                                     $item = $soldItem->item;
                                     $item->transaction_type = 'purchased';
                                     $item->other_party = $item->user->name; // 出品者名
                                     $item->unread_count = Message::where('item_id', $item->id)
                                                                 ->where('receiver_id', $user->id)
                                                                 ->where('is_read', false)
                                                                 ->count();

                                     // 最新メッセージの日時を取得
                                     $latestMessage = Message::where('item_id', $item->id)
                                                            ->where(function($query) use ($user) {
                                                                $query->where('sender_id', $user->id)
                                                                      ->orWhere('receiver_id', $user->id);
                                                            })
                                                            ->orderBy('created_at', 'desc')
                                                            ->first();

                                     $item->latest_message_at = $latestMessage ? $latestMessage->created_at : null;

                                     return $item;
                                 });

        // 出品した商品（売れた商品のみ）
        $soldItems = Item::where('user_id', $user->id)
                        ->whereHas('soldItem')
                        ->when($excludeItemId, function($query, $excludeItemId) {
                            return $query->where('id', '!=', $excludeItemId);
                        })
                        ->with(['soldItem.user'])
                        ->get()
                        ->map(function ($item) use ($user) {
                            $item->transaction_type = 'sold';
                            $item->other_party = $item->soldItem->user->name; // 購入者名
                            $item->unread_count = Message::where('item_id', $item->id)
                                                        ->where('receiver_id', $user->id)
                                                        ->where('is_read', false)
                                                        ->count();

                            // 最新メッセージの日時を取得
                            $latestMessage = Message::where('item_id', $item->id)
                                                   ->where(function($query) use ($user) {
                                                       $query->where('sender_id', $user->id)
                                                             ->orWhere('receiver_id', $user->id);
                                                   })
                                                   ->orderBy('created_at', 'desc')
                                                   ->first();

                            $item->latest_message_at = $latestMessage ? $latestMessage->created_at : null;

                            return $item;
                        });

        // 購入した商品と出品した商品を結合
        $allTransactions = $purchasedItems->concat($soldItems);

        // 最新メッセージの日時でソート（メッセージがない場合は商品の更新日時を使用）
        return $allTransactions->sortByDesc(function ($item) {
            return $item->latest_message_at ?: $item->updated_at;
        });
    }

    /**
     * メッセージを送信
     */
    public function sendMessage(Request $request, $itemId)
    {
        $request->validate([
            'message' => 'required|string|max:1000',
        ]);

        $user = User::find(Auth::id());
        $item = Item::findOrFail($itemId);

        // 購入者かどうかを確認
        $soldItem = SoldItem::where('user_id', $user->id)
                           ->where('item_id', $itemId)
                           ->first();

        if (!$soldItem && $item->user_id !== $user->id) {
            abort(403, 'この取引にメッセージを送信する権限がありません。');
        }

        // 取引完了後はメッセージ送信を禁止
        if ($soldItem && $soldItem->is_completed) {
            return redirect()->route('chat.show', $itemId)->with('error', '取引完了後はメッセージを送信できません。');
        }

        // 受信者を決定（購入者なら出品者に、出品者なら購入者に）
        if ($soldItem) {
            // 購入者の場合、出品者に送信
            $receiverId = $item->user_id;
        } else {
            // 出品者の場合、購入者に送信
            $receiverId = $soldItem ? $soldItem->user_id : SoldItem::where('item_id', $itemId)->first()->user_id;
        }

        Message::create([
            'sender_id' => $user->id,
            'receiver_id' => $receiverId,
            'item_id' => $itemId,
            'message' => $request->message,
            'is_read' => false,
        ]);

        return redirect()->route('chat.show', $itemId)->with('success', 'メッセージを送信しました。');
    }

    /**
     * メッセージを編集
     */
    public function updateMessage(Request $request, Message $message)
    {
        $request->validate([
            'message' => 'required|string|max:1000',
        ]);

        $user = User::find(Auth::id());

        // 自分のメッセージかどうかを確認
        if ($message->sender_id !== $user->id) {
            abort(403, 'このメッセージを編集する権限がありません。');
        }

        // 15分以内のメッセージのみ編集可能
        if ($message->created_at->diffInMinutes(now()) > 15) {
            return redirect()->back()->with('error', 'メッセージは送信から15分以内のみ編集可能です。');
        }

        $message->update([
            'message' => $request->message,
            'is_edited' => true,
        ]);

        return redirect()->route('chat.show', $message->item_id)->with('success', 'メッセージを編集しました。');
    }

    /**
     * メッセージを削除
     */
    public function deleteMessage(Message $message)
    {
        $user = User::find(Auth::id());

        // 自分のメッセージかどうかを確認
        if ($message->sender_id !== $user->id) {
            abort(403, 'このメッセージを削除する権限がありません。');
        }

        // 15分以内のメッセージのみ削除可能
        if ($message->created_at->diffInMinutes(now()) > 15) {
            return redirect()->back()->with('error', 'メッセージは送信から15分以内のみ削除可能です。');
        }

        $itemId = $message->item_id;
        $message->delete();

        return redirect()->route('chat.show', $itemId)->with('success', 'メッセージを削除しました。');
    }

    /**
     * 取引を完了する
     */
    public function completeTransaction(Request $request, $itemId)
    {
        $user = User::find(Auth::id());
        $item = Item::findOrFail($itemId);

        // 購入者のみが取引完了できる
        $soldItem = SoldItem::where('user_id', $user->id)
                           ->where('item_id', $itemId)
                           ->first();

        if (!$soldItem) {
            return response()->json(['error' => '取引完了権限がありません'], 403);
        }

        // 既に完了している場合は重複処理を避ける
        $wasAlreadyCompleted = $soldItem->is_completed;

        // 取引を完了状態にする
        $soldItem->update(['is_completed' => true]);

        // 初回完了時のみメール通知を送信
        if (!$wasAlreadyCompleted) {
            $seller = User::find($item->user_id);
            $buyer = $user;

            try {
                Mail::to($seller->email)->send(new TransactionCompletedMail($item, $buyer, $seller));
            } catch (\Exception $e) {
                // メール送信エラーをログに記録（取引完了は継続）
                \Log::error('取引完了メール送信エラー: ' . $e->getMessage());
            }
        }

        return response()->json(['success' => '取引が完了しました']);
    }

    public function storeRating(Request $request)
    {
        $request->validate([
            'item_id' => 'required|exists:items,id',
            'rated_user_id' => 'required|exists:users,id',
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:500',
        ]);

        $item = Item::findOrFail($request->item_id);
        $rater = auth()->user();
        $ratedUser = User::findOrFail($request->rated_user_id);

        // 取引に関わっているユーザーかチェック
        $soldItem = SoldItem::where('item_id', $item->id)->first();
        $isBuyer = $soldItem && $soldItem->user_id === $rater->id;
        $isSeller = $item->user_id === $rater->id;

        if (!$isBuyer && !$isSeller) {
            return response()->json(['error' => '評価権限がありません'], 403);
        }

        // 取引が完了しているかチェック
        if (!$soldItem || !$soldItem->is_completed) {
            return response()->json(['error' => '取引が完了していません'], 400);
        }

        // 自分自身を評価しようとしていないかチェック
        if ($rater->id === $ratedUser->id) {
            return response()->json(['error' => '自分自身を評価することはできません'], 400);
        }

        // 既に評価済みかチェック
        $existingRating = Rating::where('rater_id', $rater->id)
            ->where('rated_user_id', $ratedUser->id)
            ->where('item_id', $item->id)
            ->first();

        if ($existingRating) {
            return response()->json(['error' => '既に評価済みです'], 400);
        }

        // 評価を保存
        Rating::create([
            'rater_id' => $rater->id,
            'rated_user_id' => $ratedUser->id,
            'item_id' => $item->id,
            'rating' => $request->rating,
            'comment' => $request->comment,
        ]);

        return response()->json([
            'success' => '評価が完了しました',
            'redirect_url' => '/'
        ]);
    }
}
