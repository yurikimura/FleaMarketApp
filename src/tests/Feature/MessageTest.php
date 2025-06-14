<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\Item;
use App\Models\SoldItem;
use App\Models\Message;
use App\Models\Condition;
use App\Models\Profile;
use Illuminate\Support\Facades\Storage;

class MessageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    /**
     * 取引中の商品に関する未読メッセージ数が正しく表示されることをテスト
     */
    public function test_mypage_displays_unread_message_count_for_purchased_items()
    {
        // ユーザーを作成
        $buyer = User::factory()->create();
        $seller = User::factory()->create();
        $condition = Condition::factory()->create();

        // 購入した商品を作成
        $purchasedItem = Item::factory()->create([
            'user_id' => $seller->id,
            'condition_id' => $condition->id,
        ]);

        // 購入履歴を作成
        SoldItem::create([
            'user_id' => $buyer->id,
            'item_id' => $purchasedItem->id,
        ]);

        // 購入した商品に関する未読メッセージを作成
        Message::factory()->unread()->create([
            'sender_id' => $seller->id,
            'receiver_id' => $buyer->id,
            'item_id' => $purchasedItem->id,
            'message' => '商品の発送について',
        ]);
        Message::factory()->unread()->create([
            'sender_id' => $seller->id,
            'receiver_id' => $buyer->id,
            'item_id' => $purchasedItem->id,
            'message' => '配送状況のお知らせ',
        ]);

        // 購入していない商品に関するメッセージ（カウントされないことを確認）
        $otherItem = Item::factory()->create([
            'user_id' => $seller->id,
            'condition_id' => $condition->id,
        ]);
        Message::factory()->unread()->create([
            'sender_id' => $seller->id,
            'receiver_id' => $buyer->id,
            'item_id' => $otherItem->id,
        ]);

        // マイページにアクセス
        $response = $this->actingAs($buyer)->get('/mypage');

        $response->assertStatus(200);
        $response->assertViewHas('unreadMessageCountForPurchasedItems', 2);
        $response->assertViewHas('unreadMessageCount', 3); // 全体の未読メッセージ数
    }

    /**
     * 未読メッセージがない場合のテスト
     */
    public function test_mypage_shows_zero_when_no_unread_messages()
    {
        // ユーザーを作成
        $user = User::factory()->create();
        $sender = User::factory()->create();
        $condition = Condition::factory()->create();

        // 商品を作成
        $item = Item::factory()->create([
            'user_id' => $user->id,
            'condition_id' => $condition->id,
        ]);

        // 既読メッセージのみ作成
        Message::factory()->read()->create([
            'sender_id' => $sender->id,
            'receiver_id' => $user->id,
            'item_id' => $item->id,
        ]);

        // マイページにアクセス
        $response = $this->actingAs($user)->get('/mypage');

        $response->assertStatus(200);
        $response->assertViewHas('unreadMessageCount', 0);
        $response->assertViewHas('unreadMessageCountForPurchasedItems', 0);
    }

    /**
     * 複数の取引商品に対するメッセージ数が正しく集計されることをテスト
     */
    public function test_message_count_aggregation_for_multiple_purchased_items()
    {
        // ユーザーを作成
        $buyer = User::factory()->create();
        $seller1 = User::factory()->create();
        $seller2 = User::factory()->create();
        $condition = Condition::factory()->create();

        // 複数の購入商品を作成
        $item1 = Item::factory()->create([
            'user_id' => $seller1->id,
            'condition_id' => $condition->id,
        ]);
        $item2 = Item::factory()->create([
            'user_id' => $seller2->id,
            'condition_id' => $condition->id,
        ]);

        // 購入履歴を作成
        SoldItem::create(['user_id' => $buyer->id, 'item_id' => $item1->id]);
        SoldItem::create(['user_id' => $buyer->id, 'item_id' => $item2->id]);

        // 各商品に対する未読メッセージを作成
        Message::factory()->unread()->create([
            'sender_id' => $seller1->id,
            'receiver_id' => $buyer->id,
            'item_id' => $item1->id,
        ]);
        Message::factory()->unread()->create([
            'sender_id' => $seller1->id,
            'receiver_id' => $buyer->id,
            'item_id' => $item1->id,
        ]);
        Message::factory()->unread()->create([
            'sender_id' => $seller2->id,
            'receiver_id' => $buyer->id,
            'item_id' => $item2->id,
        ]);

        // マイページにアクセス
        $response = $this->actingAs($buyer)->get('/mypage');

        $response->assertStatus(200);
        $response->assertViewHas('unreadMessageCountForPurchasedItems', 3);
    }

    /**
     * 他のユーザーのメッセージがカウントされないことをテスト
     */
    public function test_other_users_messages_are_not_counted()
    {
        // ユーザーを作成
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $sender = User::factory()->create();
        $condition = Condition::factory()->create();

        // 商品を作成
        $item = Item::factory()->create([
            'user_id' => $sender->id,
            'condition_id' => $condition->id,
        ]);

        // user1への未読メッセージを作成
        Message::factory()->unread()->create([
            'sender_id' => $sender->id,
            'receiver_id' => $user1->id,
            'item_id' => $item->id,
        ]);

        // user2への未読メッセージを作成
        Message::factory()->unread()->create([
            'sender_id' => $sender->id,
            'receiver_id' => $user2->id,
            'item_id' => $item->id,
        ]);

        // user1のマイページにアクセス
        $response = $this->actingAs($user1)->get('/mypage');

        $response->assertStatus(200);
        $response->assertViewHas('unreadMessageCount', 1); // user1のメッセージのみカウント

        // user2のマイページにアクセス
        $response = $this->actingAs($user2)->get('/mypage');

        $response->assertStatus(200);
        $response->assertViewHas('unreadMessageCount', 1); // user2のメッセージのみカウント
    }

    /**
     * 特定の商品に関する未読メッセージ数を取得する機能のテスト
     */
    public function test_get_unread_message_count_for_specific_item()
    {
        // ユーザーを作成
        $user = User::factory()->create();
        $sender = User::factory()->create();
        $condition = Condition::factory()->create();

        // 商品を作成
        $item1 = Item::factory()->create([
            'user_id' => $user->id,
            'condition_id' => $condition->id,
        ]);
        $item2 = Item::factory()->create([
            'user_id' => $user->id,
            'condition_id' => $condition->id,
        ]);

        // item1に関する未読メッセージを作成
        Message::factory()->unread()->create([
            'sender_id' => $sender->id,
            'receiver_id' => $user->id,
            'item_id' => $item1->id,
        ]);
        Message::factory()->unread()->create([
            'sender_id' => $sender->id,
            'receiver_id' => $user->id,
            'item_id' => $item1->id,
        ]);

        // item2に関する未読メッセージを作成
        Message::factory()->unread()->create([
            'sender_id' => $sender->id,
            'receiver_id' => $user->id,
            'item_id' => $item2->id,
        ]);

        // 特定の商品に関する未読メッセージ数を確認
        $this->assertEquals(2, $user->getUnreadMessageCountForItem($item1->id));
        $this->assertEquals(1, $user->getUnreadMessageCountForItem($item2->id));
        $this->assertEquals(3, $user->getUnreadMessageCount()); // 全体
    }
}
