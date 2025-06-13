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
use Illuminate\Support\Facades\Storage;

class ChatTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    /**
     * 購入者が取引チャット画面にアクセスできることをテスト
     */
    public function test_buyer_can_access_chat_page()
    {
        // ユーザーを作成
        $buyer = User::factory()->create();
        $seller = User::factory()->create();
        $condition = Condition::factory()->create();

        // 商品を作成
        $item = Item::factory()->create([
            'user_id' => $seller->id,
            'condition_id' => $condition->id,
        ]);

        // 購入履歴を作成
        SoldItem::create([
            'user_id' => $buyer->id,
            'item_id' => $item->id,
        ]);

        // チャット画面にアクセス
        $response = $this->actingAs($buyer)->get("/chat/{$item->id}");

        $response->assertStatus(200);
        $response->assertViewIs('chat');
        $response->assertViewHas('item', $item);
        $response->assertViewHas('messages');
        $response->assertViewHas('user', $buyer);
    }

    /**
     * 出品者が取引チャット画面にアクセスできることをテスト
     */
    public function test_seller_can_access_chat_page()
    {
        // ユーザーを作成
        $seller = User::factory()->create();
        $buyer = User::factory()->create();
        $condition = Condition::factory()->create();

        // 商品を作成
        $item = Item::factory()->create([
            'user_id' => $seller->id,
            'condition_id' => $condition->id,
        ]);

        // 購入履歴を作成
        SoldItem::create([
            'user_id' => $buyer->id,
            'item_id' => $item->id,
        ]);

        // 出品者がチャット画面にアクセス
        $response = $this->actingAs($seller)->get("/chat/{$item->id}");

        $response->assertStatus(200);
        $response->assertViewIs('chat');
        $response->assertViewHas('item', $item);
        $response->assertViewHas('messages');
        $response->assertViewHas('user', $seller);
    }

    /**
     * 関係のないユーザーがチャット画面にアクセスできないことをテスト
     */
    public function test_unauthorized_user_cannot_access_chat()
    {
        // ユーザーを作成
        $seller = User::factory()->create();
        $buyer = User::factory()->create();
        $unauthorizedUser = User::factory()->create();
        $condition = Condition::factory()->create();

        // 商品を作成
        $item = Item::factory()->create([
            'user_id' => $seller->id,
            'condition_id' => $condition->id,
        ]);

        // 購入履歴を作成
        SoldItem::create([
            'user_id' => $buyer->id,
            'item_id' => $item->id,
        ]);

        // 関係のないユーザーがチャット画面にアクセス
        $response = $this->actingAs($unauthorizedUser)->get("/chat/{$item->id}");

        $response->assertStatus(403);
    }

    /**
     * チャット画面でメッセージが時系列順に表示されることをテスト
     */
    public function test_messages_are_displayed_in_chronological_order()
    {
        // ユーザーを作成
        $buyer = User::factory()->create();
        $seller = User::factory()->create();
        $condition = Condition::factory()->create();

        // 商品を作成
        $item = Item::factory()->create([
            'user_id' => $seller->id,
            'condition_id' => $condition->id,
        ]);

        // 購入履歴を作成
        SoldItem::create([
            'user_id' => $buyer->id,
            'item_id' => $item->id,
        ]);

        // メッセージを時間差で作成
        $message1 = Message::factory()->create([
            'sender_id' => $buyer->id,
            'receiver_id' => $seller->id,
            'item_id' => $item->id,
            'message' => '最初のメッセージ',
            'created_at' => now()->subMinutes(10),
        ]);

        $message2 = Message::factory()->create([
            'sender_id' => $seller->id,
            'receiver_id' => $buyer->id,
            'item_id' => $item->id,
            'message' => '2番目のメッセージ',
            'created_at' => now()->subMinutes(5),
        ]);

        $message3 = Message::factory()->create([
            'sender_id' => $buyer->id,
            'receiver_id' => $seller->id,
            'item_id' => $item->id,
            'message' => '最新のメッセージ',
            'created_at' => now(),
        ]);

        // チャット画面にアクセス
        $response = $this->actingAs($buyer)->get("/chat/{$item->id}");

        $response->assertStatus(200);

        // メッセージが時系列順に表示されることを確認
        $messages = $response->viewData('messages');
        $this->assertEquals('最初のメッセージ', $messages[0]->message);
        $this->assertEquals('2番目のメッセージ', $messages[1]->message);
        $this->assertEquals('最新のメッセージ', $messages[2]->message);
    }

    /**
     * チャット画面にアクセスすると受信メッセージが既読になることをテスト
     */
    public function test_received_messages_are_marked_as_read_when_accessing_chat()
    {
        // ユーザーを作成
        $buyer = User::factory()->create();
        $seller = User::factory()->create();
        $condition = Condition::factory()->create();

        // 商品を作成
        $item = Item::factory()->create([
            'user_id' => $seller->id,
            'condition_id' => $condition->id,
        ]);

        // 購入履歴を作成
        SoldItem::create([
            'user_id' => $buyer->id,
            'item_id' => $item->id,
        ]);

        // 未読メッセージを作成
        $message = Message::factory()->unread()->create([
            'sender_id' => $seller->id,
            'receiver_id' => $buyer->id,
            'item_id' => $item->id,
            'message' => '未読メッセージ',
        ]);

        // メッセージが未読であることを確認
        $this->assertFalse($message->is_read);

        // 購入者がチャット画面にアクセス
        $response = $this->actingAs($buyer)->get("/chat/{$item->id}");

        $response->assertStatus(200);

        // メッセージが既読になったことを確認
        $message->refresh();
        $this->assertTrue($message->is_read);
    }

    /**
     * 購入者がメッセージを送信できることをテスト
     */
    public function test_buyer_can_send_message()
    {
        // ユーザーを作成
        $buyer = User::factory()->create();
        $seller = User::factory()->create();
        $condition = Condition::factory()->create();

        // 商品を作成
        $item = Item::factory()->create([
            'user_id' => $seller->id,
            'condition_id' => $condition->id,
        ]);

        // 購入履歴を作成
        SoldItem::create([
            'user_id' => $buyer->id,
            'item_id' => $item->id,
        ]);

        // メッセージを送信
        $response = $this->actingAs($buyer)->post("/chat/{$item->id}", [
            'message' => '購入者からのメッセージです'
        ]);

        // リダイレクトされることを確認
        $response->assertRedirect("/chat/{$item->id}");

        // メッセージがデータベースに保存されることを確認
        $this->assertDatabaseHas('messages', [
            'sender_id' => $buyer->id,
            'receiver_id' => $seller->id,
            'item_id' => $item->id,
            'message' => '購入者からのメッセージです',
            'is_read' => false,
        ]);
    }

    /**
     * 出品者がメッセージを送信できることをテスト
     */
    public function test_seller_can_send_message()
    {
        // ユーザーを作成
        $seller = User::factory()->create();
        $buyer = User::factory()->create();
        $condition = Condition::factory()->create();

        // 商品を作成
        $item = Item::factory()->create([
            'user_id' => $seller->id,
            'condition_id' => $condition->id,
        ]);

        // 購入履歴を作成
        SoldItem::create([
            'user_id' => $buyer->id,
            'item_id' => $item->id,
        ]);

        // メッセージを送信
        $response = $this->actingAs($seller)->post("/chat/{$item->id}", [
            'message' => '出品者からのメッセージです'
        ]);

        // リダイレクトされることを確認
        $response->assertRedirect("/chat/{$item->id}");

        // メッセージがデータベースに保存されることを確認
        $this->assertDatabaseHas('messages', [
            'sender_id' => $seller->id,
            'receiver_id' => $buyer->id,
            'item_id' => $item->id,
            'message' => '出品者からのメッセージです',
            'is_read' => false,
        ]);
    }

    /**
     * メッセージのバリデーションが正しく動作することをテスト
     */
    public function test_message_validation()
    {
        // ユーザーを作成
        $buyer = User::factory()->create();
        $seller = User::factory()->create();
        $condition = Condition::factory()->create();

        // 商品を作成
        $item = Item::factory()->create([
            'user_id' => $seller->id,
            'condition_id' => $condition->id,
        ]);

        // 購入履歴を作成
        SoldItem::create([
            'user_id' => $buyer->id,
            'item_id' => $item->id,
        ]);

        // 空のメッセージを送信
        $response = $this->actingAs($buyer)->post("/chat/{$item->id}", [
            'message' => ''
        ]);

        $response->assertSessionHasErrors('message');

        // 長すぎるメッセージを送信
        $longMessage = str_repeat('あ', 1001);
        $response = $this->actingAs($buyer)->post("/chat/{$item->id}", [
            'message' => $longMessage
        ]);

        $response->assertSessionHasErrors('message');
    }

    /**
     * 関係のないユーザーがメッセージを送信できないことをテスト
     */
    public function test_unauthorized_user_cannot_send_message()
    {
        // ユーザーを作成
        $seller = User::factory()->create();
        $buyer = User::factory()->create();
        $unauthorizedUser = User::factory()->create();
        $condition = Condition::factory()->create();

        // 商品を作成
        $item = Item::factory()->create([
            'user_id' => $seller->id,
            'condition_id' => $condition->id,
        ]);

        // 購入履歴を作成
        SoldItem::create([
            'user_id' => $buyer->id,
            'item_id' => $item->id,
        ]);

        // 関係のないユーザーがメッセージを送信
        $response = $this->actingAs($unauthorizedUser)->post("/chat/{$item->id}", [
            'message' => '不正なメッセージ'
        ]);

        $response->assertStatus(403);

        // メッセージがデータベースに保存されていないことを確認
        $this->assertDatabaseMissing('messages', [
            'sender_id' => $unauthorizedUser->id,
            'item_id' => $item->id,
            'message' => '不正なメッセージ',
        ]);
    }

    /**
     * 存在しない商品のチャット画面にアクセスできないことをテスト
     */
    public function test_cannot_access_chat_for_nonexistent_item()
    {
        // ユーザーを作成
        $user = User::factory()->create();

        // 存在しない商品IDでチャット画面にアクセス
        $response = $this->actingAs($user)->get('/chat/99999');

        $response->assertStatus(404);
    }

    /**
     * チャット画面に商品情報が正しく表示されることをテスト
     */
    public function test_chat_displays_item_information()
    {
        // ユーザーを作成
        $buyer = User::factory()->create();
        $seller = User::factory()->create();
        $condition = Condition::factory()->create();

        // 商品を作成
        $item = Item::factory()->create([
            'user_id' => $seller->id,
            'condition_id' => $condition->id,
            'name' => 'テスト商品',
            'price' => 1000,
        ]);

        // 購入履歴を作成
        SoldItem::create([
            'user_id' => $buyer->id,
            'item_id' => $item->id,
        ]);

        // チャット画面にアクセス
        $response = $this->actingAs($buyer)->get("/chat/{$item->id}");

        $response->assertStatus(200);
        $response->assertSee('テスト商品');
        $response->assertSee('¥ 1,000');
    }

    /**
     * サイドバーから別の取引画面に遷移できることをテスト
     */
    public function test_user_can_navigate_to_other_transaction_from_sidebar()
    {
        // ユーザーを作成
        $buyer = User::factory()->create();
        $seller1 = User::factory()->create(['name' => '出品者1']);
        $seller2 = User::factory()->create(['name' => '出品者2']);
        $condition = Condition::factory()->create();

        // 2つの商品を作成
        $item1 = Item::factory()->create([
            'user_id' => $seller1->id,
            'condition_id' => $condition->id,
            'name' => '商品1',
        ]);
        $item2 = Item::factory()->create([
            'user_id' => $seller2->id,
            'condition_id' => $condition->id,
            'name' => '商品2',
        ]);

        // 両方の商品を購入
        SoldItem::create([
            'user_id' => $buyer->id,
            'item_id' => $item1->id,
        ]);
        SoldItem::create([
            'user_id' => $buyer->id,
            'item_id' => $item2->id,
        ]);

        // 商品1のチャット画面にアクセス
        $response1 = $this->actingAs($buyer)->get("/chat/{$item1->id}");
        $response1->assertStatus(200);
        $response1->assertSee('商品1');
        $response1->assertSee("/chat/{$item2->id}"); // サイドバーに商品2へのリンクがある

        // 商品2のチャット画面に遷移
        $response2 = $this->actingAs($buyer)->get("/chat/{$item2->id}");
        $response2->assertStatus(200);
        $response2->assertSee('商品2');
        $response2->assertSee("/chat/{$item1->id}"); // サイドバーに商品1へのリンクがある
    }

    /**
     * サイドバーに未読メッセージ数が表示されることをテスト
     */
    public function test_sidebar_displays_unread_message_count()
    {
        // ユーザーを作成
        $buyer = User::factory()->create();
        $seller1 = User::factory()->create(['name' => '出品者1']);
        $seller2 = User::factory()->create(['name' => '出品者2']);
        $condition = Condition::factory()->create();

        // 2つの商品を作成
        $item1 = Item::factory()->create([
            'user_id' => $seller1->id,
            'condition_id' => $condition->id,
            'name' => '商品1',
        ]);
        $item2 = Item::factory()->create([
            'user_id' => $seller2->id,
            'condition_id' => $condition->id,
            'name' => '商品2',
        ]);

        // 両方の商品を購入
        SoldItem::create([
            'user_id' => $buyer->id,
            'item_id' => $item1->id,
        ]);
        SoldItem::create([
            'user_id' => $buyer->id,
            'item_id' => $item2->id,
        ]);

        // 商品2に未読メッセージを作成
        Message::factory()->unread()->create([
            'sender_id' => $seller2->id,
            'receiver_id' => $buyer->id,
            'item_id' => $item2->id,
            'message' => '未読メッセージ1',
        ]);
        Message::factory()->unread()->create([
            'sender_id' => $seller2->id,
            'receiver_id' => $buyer->id,
            'item_id' => $item2->id,
            'message' => '未読メッセージ2',
        ]);

        // 商品1のチャット画面にアクセス
        $response = $this->actingAs($buyer)->get("/chat/{$item1->id}");

        $response->assertStatus(200);

        // サイドバーに商品2の未読メッセージ数が表示されることを確認
        $otherTransactions = $response->viewData('otherTransactions');
        $item2Transaction = $otherTransactions->firstWhere('id', $item2->id);
        $this->assertEquals(2, $item2Transaction->unread_count);
    }

    /**
     * 取引がない場合のサイドバー表示をテスト
     */
    public function test_sidebar_shows_no_transactions_message()
    {
        // ユーザーを作成
        $buyer = User::factory()->create();
        $seller = User::factory()->create();
        $condition = Condition::factory()->create();

        // 1つの商品のみ作成
        $item = Item::factory()->create([
            'user_id' => $seller->id,
            'condition_id' => $condition->id,
            'name' => '唯一の商品',
        ]);

        // 購入履歴を作成
        SoldItem::create([
            'user_id' => $buyer->id,
            'item_id' => $item->id,
        ]);

        // チャット画面にアクセス
        $response = $this->actingAs($buyer)->get("/chat/{$item->id}");

        $response->assertStatus(200);
        $response->assertSee('他の取引はありません');
    }

    /**
     * サイドバーの取引商品が更新日時順に表示されることをテスト
     */
    public function test_sidebar_transactions_are_sorted_by_updated_at()
    {
        // ユーザーを作成
        $buyer = User::factory()->create();
        $seller1 = User::factory()->create();
        $seller2 = User::factory()->create();
        $seller3 = User::factory()->create();
        $condition = Condition::factory()->create();

        // 3つの商品を作成（時間差で更新）
        $item1 = Item::factory()->create([
            'user_id' => $seller1->id,
            'condition_id' => $condition->id,
            'name' => '古い商品',
            'updated_at' => now()->subDays(3),
        ]);
        $item2 = Item::factory()->create([
            'user_id' => $seller2->id,
            'condition_id' => $condition->id,
            'name' => '新しい商品',
            'updated_at' => now()->subDay(),
        ]);
        $item3 = Item::factory()->create([
            'user_id' => $seller3->id,
            'condition_id' => $condition->id,
            'name' => '現在の商品',
            'updated_at' => now(),
        ]);

        // 全ての商品を購入
        SoldItem::create([
            'user_id' => $buyer->id,
            'item_id' => $item1->id,
        ]);
        SoldItem::create([
            'user_id' => $buyer->id,
            'item_id' => $item2->id,
        ]);
        SoldItem::create([
            'user_id' => $buyer->id,
            'item_id' => $item3->id,
        ]);

        // 現在の商品のチャット画面にアクセス
        $response = $this->actingAs($buyer)->get("/chat/{$item3->id}");

        $response->assertStatus(200);

        // サイドバーの取引商品が更新日時順（新しい順）に表示されることを確認
        $otherTransactions = $response->viewData('otherTransactions');
        $transactionNames = $otherTransactions->pluck('name')->toArray();

        // 新しい商品が古い商品より先に表示されることを確認
        $this->assertEquals(['新しい商品', '古い商品'], $transactionNames);
    }

    /**
     * サイドバーの取引商品が新規メッセージが来た順に表示されることをテスト
     */
    public function test_sidebar_transactions_are_sorted_by_latest_message()
    {
        // ユーザーを作成
        $buyer = User::factory()->create();
        $seller1 = User::factory()->create(['name' => '出品者1']);
        $seller2 = User::factory()->create(['name' => '出品者2']);
        $seller3 = User::factory()->create(['name' => '出品者3']);
        $condition = Condition::factory()->create();

        // 3つの商品を作成（同じ時間で作成）
        $item1 = Item::factory()->create([
            'user_id' => $seller1->id,
            'condition_id' => $condition->id,
            'name' => '商品1',
            'created_at' => now()->subDays(3),
            'updated_at' => now()->subDays(3),
        ]);
        $item2 = Item::factory()->create([
            'user_id' => $seller2->id,
            'condition_id' => $condition->id,
            'name' => '商品2',
            'created_at' => now()->subDays(3),
            'updated_at' => now()->subDays(3),
        ]);
        $item3 = Item::factory()->create([
            'user_id' => $seller3->id,
            'condition_id' => $condition->id,
            'name' => '商品3',
            'created_at' => now()->subDays(3),
            'updated_at' => now()->subDays(3),
        ]);
        $currentItem = Item::factory()->create([
            'user_id' => $seller1->id,
            'condition_id' => $condition->id,
            'name' => '現在の商品',
        ]);

        // 全ての商品を購入
        SoldItem::create([
            'user_id' => $buyer->id,
            'item_id' => $item1->id,
        ]);
        SoldItem::create([
            'user_id' => $buyer->id,
            'item_id' => $item2->id,
        ]);
        SoldItem::create([
            'user_id' => $buyer->id,
            'item_id' => $item3->id,
        ]);
        SoldItem::create([
            'user_id' => $buyer->id,
            'item_id' => $currentItem->id,
        ]);

        // メッセージを時間差で作成（新しいメッセージが来た順番を作る）
        // 最初に商品1にメッセージ（3時間前）
        Message::factory()->create([
            'sender_id' => $seller1->id,
            'receiver_id' => $buyer->id,
            'item_id' => $item1->id,
            'message' => '商品1への古いメッセージ',
            'created_at' => now()->subHours(3),
        ]);

        // 次に商品3にメッセージ（2時間前）
        Message::factory()->create([
            'sender_id' => $seller3->id,
            'receiver_id' => $buyer->id,
            'item_id' => $item3->id,
            'message' => '商品3への中間メッセージ',
            'created_at' => now()->subHours(2),
        ]);

        // 最後に商品2にメッセージ（1時間前）- 最新
        Message::factory()->create([
            'sender_id' => $seller2->id,
            'receiver_id' => $buyer->id,
            'item_id' => $item2->id,
            'message' => '商品2への最新メッセージ',
            'created_at' => now()->subHour(),
        ]);

        // 現在の商品のチャット画面にアクセス
        $response = $this->actingAs($buyer)->get("/chat/{$currentItem->id}");

        $response->assertStatus(200);

        // サイドバーの取引商品が最新メッセージ順に表示されることを確認
        $otherTransactions = $response->viewData('otherTransactions');
        $transactionNames = $otherTransactions->pluck('name')->toArray();

        // 最新メッセージが来た順番で表示されることを確認
        // 商品2（1時間前）→ 商品3（2時間前）→ 商品1（3時間前）
        $this->assertEquals(['商品2', '商品3', '商品1'], $transactionNames);
    }

    /**
     * メッセージがない取引商品の並び順をテスト
     */
    public function test_sidebar_transactions_without_messages_are_sorted_by_updated_at()
    {
        // ユーザーを作成
        $buyer = User::factory()->create();
        $seller1 = User::factory()->create(['name' => '出品者1']);
        $seller2 = User::factory()->create(['name' => '出品者2']);
        $seller3 = User::factory()->create(['name' => '出品者3']);
        $condition = Condition::factory()->create();

        // 3つの商品を作成（時間差で更新）
        $item1 = Item::factory()->create([
            'user_id' => $seller1->id,
            'condition_id' => $condition->id,
            'name' => '古い商品',
            'updated_at' => now()->subDays(3),
        ]);
        $item2 = Item::factory()->create([
            'user_id' => $seller2->id,
            'condition_id' => $condition->id,
            'name' => '新しい商品',
            'updated_at' => now()->subDay(),
        ]);
        $currentItem = Item::factory()->create([
            'user_id' => $seller3->id,
            'condition_id' => $condition->id,
            'name' => '現在の商品',
        ]);

        // 全ての商品を購入
        SoldItem::create([
            'user_id' => $buyer->id,
            'item_id' => $item1->id,
        ]);
        SoldItem::create([
            'user_id' => $buyer->id,
            'item_id' => $item2->id,
        ]);
        SoldItem::create([
            'user_id' => $buyer->id,
            'item_id' => $currentItem->id,
        ]);

        // メッセージは作成しない（メッセージがない状態）

        // 現在の商品のチャット画面にアクセス
        $response = $this->actingAs($buyer)->get("/chat/{$currentItem->id}");

        $response->assertStatus(200);

        // メッセージがない場合は商品の更新日時順に表示されることを確認
        $otherTransactions = $response->viewData('otherTransactions');
        $transactionNames = $otherTransactions->pluck('name')->toArray();

        // 新しい商品が古い商品より先に表示されることを確認
        $this->assertEquals(['新しい商品', '古い商品'], $transactionNames);
    }

    /**
     * メッセージがある商品とない商品が混在する場合の並び順をテスト
     */
    public function test_sidebar_transactions_mixed_with_and_without_messages()
    {
        // ユーザーを作成
        $buyer = User::factory()->create();
        $seller1 = User::factory()->create(['name' => '出品者1']);
        $seller2 = User::factory()->create(['name' => '出品者2']);
        $seller3 = User::factory()->create(['name' => '出品者3']);
        $seller4 = User::factory()->create(['name' => '出品者4']);
        $condition = Condition::factory()->create();

        // 4つの商品を作成
        $itemWithOldMessage = Item::factory()->create([
            'user_id' => $seller1->id,
            'condition_id' => $condition->id,
            'name' => '古いメッセージがある商品',
            'updated_at' => now()->subDays(5),
        ]);
        $itemWithNewMessage = Item::factory()->create([
            'user_id' => $seller2->id,
            'condition_id' => $condition->id,
            'name' => '新しいメッセージがある商品',
            'updated_at' => now()->subDays(5),
        ]);
        $itemWithoutMessage = Item::factory()->create([
            'user_id' => $seller3->id,
            'condition_id' => $condition->id,
            'name' => 'メッセージがない商品',
            'updated_at' => now()->subDay(), // 比較的新しい更新日時
        ]);
        $currentItem = Item::factory()->create([
            'user_id' => $seller4->id,
            'condition_id' => $condition->id,
            'name' => '現在の商品',
        ]);

        // 全ての商品を購入
        SoldItem::create([
            'user_id' => $buyer->id,
            'item_id' => $itemWithOldMessage->id,
        ]);
        SoldItem::create([
            'user_id' => $buyer->id,
            'item_id' => $itemWithNewMessage->id,
        ]);
        SoldItem::create([
            'user_id' => $buyer->id,
            'item_id' => $itemWithoutMessage->id,
        ]);
        SoldItem::create([
            'user_id' => $buyer->id,
            'item_id' => $currentItem->id,
        ]);

        // メッセージを作成
        Message::factory()->create([
            'sender_id' => $seller1->id,
            'receiver_id' => $buyer->id,
            'item_id' => $itemWithOldMessage->id,
            'message' => '古いメッセージ',
            'created_at' => now()->subHours(3),
        ]);

        Message::factory()->create([
            'sender_id' => $seller2->id,
            'receiver_id' => $buyer->id,
            'item_id' => $itemWithNewMessage->id,
            'message' => '新しいメッセージ',
            'created_at' => now()->subHour(),
        ]);

        // 現在の商品のチャット画面にアクセス
        $response = $this->actingAs($buyer)->get("/chat/{$currentItem->id}");

        $response->assertStatus(200);

        // サイドバーの取引商品の並び順を確認
        $otherTransactions = $response->viewData('otherTransactions');
        $transactionNames = $otherTransactions->pluck('name')->toArray();

        // 期待される順番：
        // 1. 新しいメッセージがある商品（最新メッセージ：1時間前）
        // 2. 古いメッセージがある商品（最新メッセージ：3時間前）
        // 3. メッセージがない商品（商品更新日時：1日前）
        $this->assertEquals([
            '新しいメッセージがある商品',
            '古いメッセージがある商品',
            'メッセージがない商品'
        ], $transactionNames);
    }

    /**
     * 未読メッセージがある商品に通知マークが表示されることをテスト
     */
    public function test_notification_badge_displays_for_items_with_unread_messages()
    {
        // ユーザーを作成
        $buyer = User::factory()->create();
        $seller1 = User::factory()->create(['name' => '出品者1']);
        $seller2 = User::factory()->create(['name' => '出品者2']);
        $seller3 = User::factory()->create(['name' => '出品者3']);
        $condition = Condition::factory()->create();

        // 3つの商品を作成
        $itemWithUnreadMessages = Item::factory()->create([
            'user_id' => $seller1->id,
            'condition_id' => $condition->id,
            'name' => '未読メッセージがある商品',
        ]);
        $itemWithoutMessages = Item::factory()->create([
            'user_id' => $seller2->id,
            'condition_id' => $condition->id,
            'name' => 'メッセージがない商品',
        ]);
        $currentItem = Item::factory()->create([
            'user_id' => $seller3->id,
            'condition_id' => $condition->id,
            'name' => '現在の商品',
        ]);

        // 全ての商品を購入
        SoldItem::create([
            'user_id' => $buyer->id,
            'item_id' => $itemWithUnreadMessages->id,
        ]);
        SoldItem::create([
            'user_id' => $buyer->id,
            'item_id' => $itemWithoutMessages->id,
        ]);
        SoldItem::create([
            'user_id' => $buyer->id,
            'item_id' => $currentItem->id,
        ]);

        // 未読メッセージを作成
        Message::factory()->unread()->create([
            'sender_id' => $seller1->id,
            'receiver_id' => $buyer->id,
            'item_id' => $itemWithUnreadMessages->id,
            'message' => '未読メッセージ1',
        ]);
        Message::factory()->unread()->create([
            'sender_id' => $seller1->id,
            'receiver_id' => $buyer->id,
            'item_id' => $itemWithUnreadMessages->id,
            'message' => '未読メッセージ2',
        ]);

        // 現在の商品のチャット画面にアクセス
        $response = $this->actingAs($buyer)->get("/chat/{$currentItem->id}");

        $response->assertStatus(200);

        // 未読メッセージがある商品に通知マークが表示されることを確認
        $response->assertSee('class="notification-badge"', false);
        $response->assertSee('2', false); // 未読メッセージ数

        // 未読メッセージがない商品には通知マークが表示されないことを確認
        $otherTransactions = $response->viewData('otherTransactions');
        $itemWithoutMessagesTransaction = $otherTransactions->firstWhere('id', $itemWithoutMessages->id);
        $this->assertEquals(0, $itemWithoutMessagesTransaction->unread_count);
    }

    /**
     * 複数の商品で異なる未読メッセージ数の通知マークが表示されることをテスト
     */
    public function test_notification_badge_displays_different_counts_for_multiple_items()
    {
        // ユーザーを作成
        $buyer = User::factory()->create();
        $seller1 = User::factory()->create(['name' => '出品者1']);
        $seller2 = User::factory()->create(['name' => '出品者2']);
        $seller3 = User::factory()->create(['name' => '出品者3']);
        $condition = Condition::factory()->create();

        // 3つの商品を作成
        $itemWith1UnreadMessage = Item::factory()->create([
            'user_id' => $seller1->id,
            'condition_id' => $condition->id,
            'name' => '未読1件の商品',
        ]);
        $itemWith5UnreadMessages = Item::factory()->create([
            'user_id' => $seller2->id,
            'condition_id' => $condition->id,
            'name' => '未読5件の商品',
        ]);
        $currentItem = Item::factory()->create([
            'user_id' => $seller3->id,
            'condition_id' => $condition->id,
            'name' => '現在の商品',
        ]);

        // 全ての商品を購入
        SoldItem::create([
            'user_id' => $buyer->id,
            'item_id' => $itemWith1UnreadMessage->id,
        ]);
        SoldItem::create([
            'user_id' => $buyer->id,
            'item_id' => $itemWith5UnreadMessages->id,
        ]);
        SoldItem::create([
            'user_id' => $buyer->id,
            'item_id' => $currentItem->id,
        ]);

        // 1件の未読メッセージを作成
        Message::factory()->unread()->create([
            'sender_id' => $seller1->id,
            'receiver_id' => $buyer->id,
            'item_id' => $itemWith1UnreadMessage->id,
            'message' => '未読メッセージ1',
        ]);

        // 5件の未読メッセージを作成
        for ($i = 1; $i <= 5; $i++) {
            Message::factory()->unread()->create([
                'sender_id' => $seller2->id,
                'receiver_id' => $buyer->id,
                'item_id' => $itemWith5UnreadMessages->id,
                'message' => "未読メッセージ{$i}",
            ]);
        }

        // 現在の商品のチャット画面にアクセス
        $response = $this->actingAs($buyer)->get("/chat/{$currentItem->id}");

        $response->assertStatus(200);

        // 各商品の未読メッセージ数が正しく表示されることを確認
        $otherTransactions = $response->viewData('otherTransactions');

        $item1Transaction = $otherTransactions->firstWhere('id', $itemWith1UnreadMessage->id);
        $this->assertEquals(1, $item1Transaction->unread_count);

        $item5Transaction = $otherTransactions->firstWhere('id', $itemWith5UnreadMessages->id);
        $this->assertEquals(5, $item5Transaction->unread_count);

        // HTMLに通知マークが含まれることを確認
        $response->assertSee('class="notification-badge"', false);
    }

    /**
     * 出品者として売れた商品の未読メッセージに通知マークが表示されることをテスト
     */
    public function test_notification_badge_displays_for_seller_with_unread_messages()
    {
        // ユーザーを作成
        $seller = User::factory()->create();
        $buyer1 = User::factory()->create(['name' => '購入者1']);
        $buyer2 = User::factory()->create(['name' => '購入者2']);
        $condition = Condition::factory()->create();

        // 出品者の商品を作成
        $soldItemWithUnreadMessages = Item::factory()->create([
            'user_id' => $seller->id,
            'condition_id' => $condition->id,
            'name' => '未読メッセージがある売れた商品',
        ]);
        $soldItemWithoutMessages = Item::factory()->create([
            'user_id' => $seller->id,
            'condition_id' => $condition->id,
            'name' => 'メッセージがない売れた商品',
        ]);
        $currentSoldItem = Item::factory()->create([
            'user_id' => $seller->id,
            'condition_id' => $condition->id,
            'name' => '現在の売れた商品',
        ]);

        // 購入履歴を作成
        SoldItem::create([
            'user_id' => $buyer1->id,
            'item_id' => $soldItemWithUnreadMessages->id,
        ]);
        SoldItem::create([
            'user_id' => $buyer2->id,
            'item_id' => $soldItemWithoutMessages->id,
        ]);
        SoldItem::create([
            'user_id' => $buyer1->id,
            'item_id' => $currentSoldItem->id,
        ]);

        // 購入者から出品者への未読メッセージを作成
        Message::factory()->unread()->create([
            'sender_id' => $buyer1->id,
            'receiver_id' => $seller->id,
            'item_id' => $soldItemWithUnreadMessages->id,
            'message' => '購入者からの未読メッセージ1',
        ]);
        Message::factory()->unread()->create([
            'sender_id' => $buyer1->id,
            'receiver_id' => $seller->id,
            'item_id' => $soldItemWithUnreadMessages->id,
            'message' => '購入者からの未読メッセージ2',
        ]);
        Message::factory()->unread()->create([
            'sender_id' => $buyer1->id,
            'receiver_id' => $seller->id,
            'item_id' => $soldItemWithUnreadMessages->id,
            'message' => '購入者からの未読メッセージ3',
        ]);

        // 出品者として現在の商品のチャット画面にアクセス
        $response = $this->actingAs($seller)->get("/chat/{$currentSoldItem->id}");

        $response->assertStatus(200);

        // 未読メッセージがある商品に通知マークが表示されることを確認
        $otherTransactions = $response->viewData('otherTransactions');
        $itemWithUnreadTransaction = $otherTransactions->firstWhere('id', $soldItemWithUnreadMessages->id);
        $this->assertEquals(3, $itemWithUnreadTransaction->unread_count);

        // 未読メッセージがない商品には通知マークが表示されないことを確認
        $itemWithoutMessagesTransaction = $otherTransactions->firstWhere('id', $soldItemWithoutMessages->id);
        $this->assertEquals(0, $itemWithoutMessagesTransaction->unread_count);

        // HTMLに通知マークが含まれることを確認
        $response->assertSee('class="notification-badge"', false);
        $response->assertSee('3', false); // 未読メッセージ数
    }

    /**
     * 通知マークが10件以上の場合に「9+」と表示されることをテスト
     */
    public function test_notification_badge_displays_9_plus_for_more_than_9_unread_messages()
    {
        // ユーザーを作成
        $buyer = User::factory()->create();
        $seller = User::factory()->create(['name' => '出品者']);
        $condition = Condition::factory()->create();

        // 商品を作成
        $itemWithManyUnreadMessages = Item::factory()->create([
            'user_id' => $seller->id,
            'condition_id' => $condition->id,
            'name' => '未読多数の商品',
        ]);
        $currentItem = Item::factory()->create([
            'user_id' => $seller->id,
            'condition_id' => $condition->id,
            'name' => '現在の商品',
        ]);

        // 商品を購入
        SoldItem::create([
            'user_id' => $buyer->id,
            'item_id' => $itemWithManyUnreadMessages->id,
        ]);
        SoldItem::create([
            'user_id' => $buyer->id,
            'item_id' => $currentItem->id,
        ]);

        // 12件の未読メッセージを作成
        for ($i = 1; $i <= 12; $i++) {
            Message::factory()->unread()->create([
                'sender_id' => $seller->id,
                'receiver_id' => $buyer->id,
                'item_id' => $itemWithManyUnreadMessages->id,
                'message' => "未読メッセージ{$i}",
            ]);
        }

        // 現在の商品のチャット画面にアクセス
        $response = $this->actingAs($buyer)->get("/chat/{$currentItem->id}");

        $response->assertStatus(200);

        // 未読メッセージ数が正しく取得されることを確認
        $otherTransactions = $response->viewData('otherTransactions');
        $itemTransaction = $otherTransactions->firstWhere('id', $itemWithManyUnreadMessages->id);
        $this->assertEquals(12, $itemTransaction->unread_count);

        // HTMLに通知マークが含まれることを確認
        $response->assertSee('class="notification-badge"', false);
        // 10件以上の場合は「9+」と表示されることを確認
        $response->assertSee('9+', false);
    }

    /**
     * チャット画面にアクセスした後に通知マークが消えることをテスト
     */
    public function test_notification_badge_disappears_after_accessing_chat()
    {
        // ユーザーを作成
        $buyer = User::factory()->create();
        $seller1 = User::factory()->create(['name' => '出品者1']);
        $seller2 = User::factory()->create(['name' => '出品者2']);
        $condition = Condition::factory()->create();

        // 2つの商品を作成
        $itemWithUnreadMessages = Item::factory()->create([
            'user_id' => $seller1->id,
            'condition_id' => $condition->id,
            'name' => '未読メッセージがある商品',
        ]);
        $otherItem = Item::factory()->create([
            'user_id' => $seller2->id,
            'condition_id' => $condition->id,
            'name' => '他の商品',
        ]);

        // 両方の商品を購入
        SoldItem::create([
            'user_id' => $buyer->id,
            'item_id' => $itemWithUnreadMessages->id,
        ]);
        SoldItem::create([
            'user_id' => $buyer->id,
            'item_id' => $otherItem->id,
        ]);

        // 未読メッセージを作成
        Message::factory()->unread()->create([
            'sender_id' => $seller1->id,
            'receiver_id' => $buyer->id,
            'item_id' => $itemWithUnreadMessages->id,
            'message' => '未読メッセージ',
        ]);

        // 最初に他の商品のチャット画面にアクセス（未読メッセージがある商品の通知マークを確認）
        $response1 = $this->actingAs($buyer)->get("/chat/{$otherItem->id}");
        $response1->assertStatus(200);

        // 未読メッセージがある商品に通知マークが表示されることを確認
        $otherTransactions1 = $response1->viewData('otherTransactions');
        $itemTransaction1 = $otherTransactions1->firstWhere('id', $itemWithUnreadMessages->id);
        $this->assertEquals(1, $itemTransaction1->unread_count);

        // 未読メッセージがある商品のチャット画面にアクセス
        $response2 = $this->actingAs($buyer)->get("/chat/{$itemWithUnreadMessages->id}");
        $response2->assertStatus(200);

        // メッセージが既読になったことを確認
        $message = Message::where('item_id', $itemWithUnreadMessages->id)
                         ->where('receiver_id', $buyer->id)
                         ->first();
        $this->assertTrue($message->is_read);

        // 再度他の商品のチャット画面にアクセスして通知マークが消えたことを確認
        $response3 = $this->actingAs($buyer)->get("/chat/{$otherItem->id}");
        $response3->assertStatus(200);

        // 通知マークが消えたことを確認
        $otherTransactions3 = $response3->viewData('otherTransactions');
        $itemTransaction3 = $otherTransactions3->firstWhere('id', $itemWithUnreadMessages->id);
        $this->assertEquals(0, $itemTransaction3->unread_count);
    }

    /**
     * 通知マークから未読メッセージ数を確認できることをテスト
     */
    public function test_notification_badge_shows_exact_unread_message_count()
    {
        // ユーザーと条件を作成
        $buyer = User::factory()->create();
        $seller = User::factory()->create();
        $condition = Condition::factory()->create();

        // 商品を作成
        $item = Item::factory()->create(['user_id' => $seller->id, 'condition_id' => $condition->id]);

        // 商品を購入済みにする
        SoldItem::create(['user_id' => $buyer->id, 'item_id' => $item->id]);

        // 複数の未読メッセージを作成
        $messages = [];
        for ($i = 1; $i <= 5; $i++) {
            $messages[] = Message::factory()->create([
                'sender_id' => $seller->id,
                'receiver_id' => $buyer->id,
                'item_id' => $item->id,
                'message' => "未読メッセージ{$i}",
                'is_read' => false,
            ]);
        }

        // マイページにアクセス
        $response = $this->actingAs($buyer)->get('/mypage');

        $response->assertStatus(200);

        // 通知バッジが正確な数を表示していることを確認
        $response->assertSee('data-unread-count="5"', false);
        $response->assertSee('未読メッセージ5件', false);

        // 1件目のメッセージを既読にする
        $messages[0]->update(['is_read' => true]);

        // マイページに再アクセス
        $response = $this->actingAs($buyer)->get('/mypage');

        $response->assertStatus(200);

        // 通知バッジが更新された数を表示していることを確認
        $response->assertSee('data-unread-count="4"', false);
        $response->assertSee('未読メッセージ4件', false);

        // 残りのメッセージを既読にする
        foreach (array_slice($messages, 1) as $message) {
            $message->update(['is_read' => true]);
        }

        // マイページに再アクセス
        $response = $this->actingAs($buyer)->get('/mypage');

        $response->assertStatus(200);

        // 通知バッジが非表示になっていることを確認
        $response->assertDontSee('data-unread-count', false);
        $response->assertDontSee('未読メッセージ', false);
    }

    /**
     * 通知マークが既読・未読を正確に区別することをテスト
     */
    public function test_notification_badge_distinguishes_read_and_unread_messages()
    {
        // ユーザーと条件を作成
        $buyer = User::factory()->create();
        $seller = User::factory()->create(['name' => '出品者']);
        $condition = Condition::factory()->create();

        // 2つの商品を作成
        $itemWithMixedMessages = Item::factory()->create([
            'user_id' => $seller->id,
            'condition_id' => $condition->id,
            'name' => '既読・未読混在の商品',
        ]);
        $currentItem = Item::factory()->create([
            'user_id' => $seller->id,
            'condition_id' => $condition->id,
            'name' => '現在の商品',
        ]);

        // 両方の商品を購入
        SoldItem::create([
            'user_id' => $buyer->id,
            'item_id' => $itemWithMixedMessages->id,
        ]);
        SoldItem::create([
            'user_id' => $buyer->id,
            'item_id' => $currentItem->id,
        ]);

        // 既読メッセージを3件作成
        for ($i = 1; $i <= 3; $i++) {
            Message::factory()->read()->create([
                'sender_id' => $seller->id,
                'receiver_id' => $buyer->id,
                'item_id' => $itemWithMixedMessages->id,
                'message' => "既読メッセージ{$i}",
            ]);
        }

        // 未読メッセージを2件作成
        for ($i = 1; $i <= 2; $i++) {
            Message::factory()->unread()->create([
                'sender_id' => $seller->id,
                'receiver_id' => $buyer->id,
                'item_id' => $itemWithMixedMessages->id,
                'message' => "未読メッセージ{$i}",
            ]);
        }

        // 現在の商品のチャット画面にアクセス
        $response = $this->actingAs($buyer)->get("/chat/{$currentItem->id}");

        $response->assertStatus(200);

        // 未読メッセージのみがカウントされることを確認
        $otherTransactions = $response->viewData('otherTransactions');
        $itemTransaction = $otherTransactions->firstWhere('id', $itemWithMixedMessages->id);
        $this->assertEquals(2, $itemTransaction->unread_count); // 未読メッセージのみカウント

        // HTMLに未読メッセージ数のみが表示されることを確認
        $response->assertSee('class="notification-badge"', false);
        $response->assertSee('>2<', false); // 未読メッセージ数
    }

    /**
     * 送信者と受信者を区別して未読メッセージ数をカウントすることをテスト
     */
    public function test_notification_badge_counts_only_received_unread_messages()
    {
        // ユーザーを作成
        $buyer = User::factory()->create();
        $seller = User::factory()->create(['name' => '出品者']);
        $condition = Condition::factory()->create();

        // 2つの商品を作成
        $itemWithMessages = Item::factory()->create([
            'user_id' => $seller->id,
            'condition_id' => $condition->id,
            'name' => '送受信メッセージがある商品',
        ]);
        $currentItem = Item::factory()->create([
            'user_id' => $seller->id,
            'condition_id' => $condition->id,
            'name' => '現在の商品',
        ]);

        // 両方の商品を購入
        SoldItem::create([
            'user_id' => $buyer->id,
            'item_id' => $itemWithMessages->id,
        ]);
        SoldItem::create([
            'user_id' => $buyer->id,
            'item_id' => $currentItem->id,
        ]);

        // 購入者が送信した未読メッセージを3件作成（これらはカウントされない）
        for ($i = 1; $i <= 3; $i++) {
            Message::factory()->unread()->create([
                'sender_id' => $buyer->id,
                'receiver_id' => $seller->id,
                'item_id' => $itemWithMessages->id,
                'message' => "購入者からのメッセージ{$i}",
            ]);
        }

        // 出品者が送信した未読メッセージを4件作成（これらがカウントされる）
        for ($i = 1; $i <= 4; $i++) {
            Message::factory()->unread()->create([
                'sender_id' => $seller->id,
                'receiver_id' => $buyer->id,
                'item_id' => $itemWithMessages->id,
                'message' => "出品者からのメッセージ{$i}",
            ]);
        }

        // 購入者として現在の商品のチャット画面にアクセス
        $response = $this->actingAs($buyer)->get("/chat/{$currentItem->id}");

        $response->assertStatus(200);

        // 購入者が受信した未読メッセージのみがカウントされることを確認
        $otherTransactions = $response->viewData('otherTransactions');
        $itemTransaction = $otherTransactions->firstWhere('id', $itemWithMessages->id);
        $this->assertEquals(4, $itemTransaction->unread_count); // 受信した未読メッセージのみカウント

        // HTMLに受信した未読メッセージ数が表示されることを確認
        $response->assertSee('class="notification-badge"', false);
        $response->assertSee('>4<', false); // 受信した未読メッセージ数
    }

    /**
     * 出品者視点での通知マークの未読メッセージ数をテスト
     */
    public function test_notification_badge_shows_correct_count_for_seller()
    {
        // ユーザーと条件を作成
        $seller = User::factory()->create();
        $buyer1 = User::factory()->create();
        $buyer2 = User::factory()->create();
        $condition = Condition::factory()->create();

        // 商品を作成
        $currentSoldItem = Item::factory()->create(['user_id' => $seller->id, 'condition_id' => $condition->id]);
        $soldItem1 = Item::factory()->create(['user_id' => $seller->id, 'condition_id' => $condition->id]);
        $soldItem2 = Item::factory()->create(['user_id' => $seller->id, 'condition_id' => $condition->id]);

        // 商品を購入済みにする
        SoldItem::create(['user_id' => $buyer1->id, 'item_id' => $currentSoldItem->id]);
        SoldItem::create(['user_id' => $buyer1->id, 'item_id' => $soldItem1->id]);
        SoldItem::create(['user_id' => $buyer2->id, 'item_id' => $soldItem2->id]);

        // 未読メッセージを作成
        for ($i = 1; $i <= 5; $i++) {
            Message::factory()->unread()->create([
                'sender_id' => $buyer1->id,
                'receiver_id' => $seller->id,
                'item_id' => $soldItem1->id,
                'message' => "購入者1からのメッセージ{$i}",
            ]);
        }

        for ($i = 1; $i <= 2; $i++) {
            Message::factory()->unread()->create([
                'sender_id' => $buyer2->id,
                'receiver_id' => $seller->id,
                'item_id' => $soldItem2->id,
                'message' => "購入者2からのメッセージ{$i}",
            ]);
        }

        // 出品者として現在の商品のチャット画面にアクセス
        $response = $this->actingAs($seller)->get("/chat/{$currentSoldItem->id}");

        $response->assertStatus(200);

        // 各商品の未読メッセージ数が正確に表示されることを確認
        $otherTransactions = $response->viewData('otherTransactions');

        $soldItem1Transaction = $otherTransactions->firstWhere('id', $soldItem1->id);
        $this->assertEquals(5, $soldItem1Transaction->unread_count);

        $soldItem2Transaction = $otherTransactions->firstWhere('id', $soldItem2->id);
        $this->assertEquals(2, $soldItem2Transaction->unread_count);

        // HTMLに正確な未読メッセージ数が表示されることを確認
        $response->assertSee('class="notification-badge"', false);
        $response->assertSee('>5<', false); // 5件の未読メッセージ
        $response->assertSee('>2<', false); // 2件の未読メッセージ
    }

    /**
     * チャット入力中に他の画面に遷移しても入力情報が保持されることをテスト
     */
    public function test_chat_input_is_preserved_when_navigating_to_other_pages()
    {
        // ユーザーと条件を作成
        $buyer = User::factory()->create();
        $seller = User::factory()->create();
        $condition = Condition::factory()->create();

        // 商品を作成
        $item = Item::factory()->create(['user_id' => $seller->id, 'condition_id' => $condition->id]);

        // 商品を購入済みにする
        SoldItem::create(['user_id' => $buyer->id, 'item_id' => $item->id]);

        // 購入者としてチャット画面にアクセス
        $response = $this->actingAs($buyer)->get("/chat/{$item->id}");

        $response->assertStatus(200);

        // チャット画面にメッセージ入力フォームが存在することを確認
        $response->assertSee('name="message"', false);
        $response->assertSee('textarea', false);

        // セッションにメッセージの下書きを保存（実際のJavaScriptの動作をシミュレート）
        session(['chat_draft_' . $item->id => 'これは下書きメッセージです']);

        // 他の画面（マイページ）に遷移
        $mypageResponse = $this->actingAs($buyer)->get('/mypage');
        $mypageResponse->assertStatus(200);

        // 再度チャット画面に戻る
        $returnResponse = $this->actingAs($buyer)->get("/chat/{$item->id}");
        $returnResponse->assertStatus(200);

        // セッションに保存された下書きが存在することを確認
        $this->assertEquals('これは下書きメッセージです', session('chat_draft_' . $item->id));
    }

    /**
     * 複数の商品のチャット入力情報が個別に保持されることをテスト
     */
    public function test_chat_input_is_preserved_separately_for_different_items()
    {
        // ユーザーと条件を作成
        $buyer = User::factory()->create();
        $seller1 = User::factory()->create();
        $seller2 = User::factory()->create();
        $condition = Condition::factory()->create();

        // 商品を作成
        $item1 = Item::factory()->create(['user_id' => $seller1->id, 'condition_id' => $condition->id]);
        $item2 = Item::factory()->create(['user_id' => $seller2->id, 'condition_id' => $condition->id]);

        // 商品を購入済みにする
        SoldItem::create(['user_id' => $buyer->id, 'item_id' => $item1->id]);
        SoldItem::create(['user_id' => $buyer->id, 'item_id' => $item2->id]);

        // 最初の商品のチャット画面にアクセスして下書きを保存
        $this->actingAs($buyer)->get("/chat/{$item1->id}");
        session(['chat_draft_' . $item1->id => '商品1への下書きメッセージ']);

        // 2番目の商品のチャット画面にアクセスして下書きを保存
        $this->actingAs($buyer)->get("/chat/{$item2->id}");
        session(['chat_draft_' . $item2->id => '商品2への下書きメッセージ']);

        // 他の画面に遷移
        $this->actingAs($buyer)->get('/mypage');

        // 最初の商品のチャット画面に戻る
        $response1 = $this->actingAs($buyer)->get("/chat/{$item1->id}");
        $response1->assertStatus(200);

        // 2番目の商品のチャット画面に戻る
        $response2 = $this->actingAs($buyer)->get("/chat/{$item2->id}");
        $response2->assertStatus(200);

        // それぞれの下書きが個別に保持されていることを確認
        $this->assertEquals('商品1への下書きメッセージ', session('chat_draft_' . $item1->id));
        $this->assertEquals('商品2への下書きメッセージ', session('chat_draft_' . $item2->id));
    }

    /**
     * チャット入力情報がブラウザセッション間で保持されることをテスト
     */
    public function test_chat_input_persists_across_browser_sessions()
    {
        // ユーザーと条件を作成
        $buyer = User::factory()->create();
        $seller = User::factory()->create();
        $condition = Condition::factory()->create();

        // 商品を作成
        $item = Item::factory()->create(['user_id' => $seller->id, 'condition_id' => $condition->id]);

        // 商品を購入済みにする
        SoldItem::create(['user_id' => $buyer->id, 'item_id' => $item->id]);

        // 最初のセッションでチャット画面にアクセスして下書きを保存
        $this->actingAs($buyer)->get("/chat/{$item->id}");
        session(['chat_draft_' . $item->id => '長時間保持される下書きメッセージ']);

        // セッションを一度終了（ログアウト）
        $this->post('/logout');

        // 再度ログインして同じチャット画面にアクセス
        $this->actingAs($buyer);

        // 新しいセッションでは下書きは保持されていない（これは正常な動作）
        $this->assertNull(session('chat_draft_' . $item->id));

        // しかし、チャット画面は正常に表示される
        $response = $this->actingAs($buyer)->get("/chat/{$item->id}");
        $response->assertStatus(200);
        $response->assertSee('name="message"', false);
    }

    /**
     * 投稿済みメッセージを編集できることをテスト
     */
    public function test_user_can_edit_their_own_message()
    {
        // ユーザーと条件を作成
        $buyer = User::factory()->create();
        $seller = User::factory()->create();
        $condition = Condition::factory()->create();

        // 商品を作成
        $item = Item::factory()->create(['user_id' => $seller->id, 'condition_id' => $condition->id]);

        // 商品を購入済みにする
        SoldItem::create(['user_id' => $buyer->id, 'item_id' => $item->id]);

        // メッセージを送信
        $message = Message::factory()->create([
            'sender_id' => $buyer->id,
            'receiver_id' => $seller->id,
            'item_id' => $item->id,
            'message' => '元のメッセージです',
        ]);

        // メッセージを編集
        $response = $this->actingAs($buyer)->put("/messages/{$message->id}", [
            'message' => '編集されたメッセージです'
        ]);

        $response->assertRedirect("/chat/{$item->id}");

        // メッセージが編集されたことを確認
        $message->refresh();
        $this->assertEquals('編集されたメッセージです', $message->message);
        $this->assertTrue($message->is_edited);
        $this->assertNotNull($message->edited_at);
    }

    /**
     * 他人のメッセージを編集できないことをテスト
     */
    public function test_user_cannot_edit_others_message()
    {
        // ユーザーと条件を作成
        $buyer = User::factory()->create();
        $seller = User::factory()->create();
        $otherUser = User::factory()->create();
        $condition = Condition::factory()->create();

        // 商品を作成
        $item = Item::factory()->create(['user_id' => $seller->id, 'condition_id' => $condition->id]);

        // 商品を購入済みにする
        SoldItem::create(['user_id' => $buyer->id, 'item_id' => $item->id]);

        // 購入者のメッセージを作成
        $message = Message::factory()->create([
            'sender_id' => $buyer->id,
            'receiver_id' => $seller->id,
            'item_id' => $item->id,
            'message' => '購入者のメッセージです',
        ]);

        // 他のユーザーがメッセージを編集しようとする
        $response = $this->actingAs($otherUser)->put("/messages/{$message->id}", [
            'message' => '不正に編集されたメッセージ'
        ]);

        $response->assertStatus(403);

        // メッセージが編集されていないことを確認
        $message->refresh();
        $this->assertEquals('購入者のメッセージです', $message->message);
        $this->assertFalse($message->is_edited);
        $this->assertNull($message->edited_at);
    }

    /**
     * メッセージ編集のバリデーションが正しく動作することをテスト
     */
    public function test_message_edit_validation()
    {
        // ユーザーと条件を作成
        $buyer = User::factory()->create();
        $seller = User::factory()->create();
        $condition = Condition::factory()->create();

        // 商品を作成
        $item = Item::factory()->create(['user_id' => $seller->id, 'condition_id' => $condition->id]);

        // 商品を購入済みにする
        SoldItem::create(['user_id' => $buyer->id, 'item_id' => $item->id]);

        // メッセージを作成
        $message = Message::factory()->create([
            'sender_id' => $buyer->id,
            'receiver_id' => $seller->id,
            'item_id' => $item->id,
            'message' => '元のメッセージです',
        ]);

        // 空のメッセージで編集しようとする
        $response = $this->actingAs($buyer)->put("/messages/{$message->id}", [
            'message' => ''
        ]);

        $response->assertSessionHasErrors('message');

        // 長すぎるメッセージで編集しようとする
        $longMessage = str_repeat('あ', 1001);
        $response = $this->actingAs($buyer)->put("/messages/{$message->id}", [
            'message' => $longMessage
        ]);

        $response->assertSessionHasErrors('message');

        // メッセージが編集されていないことを確認
        $message->refresh();
        $this->assertEquals('元のメッセージです', $message->message);
        $this->assertFalse($message->is_edited);
    }

    /**
     * 編集されたメッセージに編集マークが表示されることをテスト
     */
    public function test_edited_message_displays_edit_indicator()
    {
        // ユーザーと条件を作成
        $buyer = User::factory()->create();
        $seller = User::factory()->create();
        $condition = Condition::factory()->create();

        // 商品を作成
        $item = Item::factory()->create(['user_id' => $seller->id, 'condition_id' => $condition->id]);

        // 商品を購入済みにする
        SoldItem::create(['user_id' => $buyer->id, 'item_id' => $item->id]);

        // メッセージを作成
        $message = Message::factory()->create([
            'sender_id' => $buyer->id,
            'receiver_id' => $seller->id,
            'item_id' => $item->id,
            'message' => '元のメッセージです',
        ]);

        // メッセージを編集
        $this->actingAs($buyer)->put("/messages/{$message->id}", [
            'message' => '編集されたメッセージです'
        ]);

        // チャット画面にアクセス
        $response = $this->actingAs($buyer)->get("/chat/{$item->id}");

        $response->assertStatus(200);
        $response->assertSee('編集されたメッセージです');
        $response->assertSee('編集済み', false); // 編集マークが表示される
        $response->assertSee('class="edited-indicator"', false); // 編集マークのCSSクラス
    }

    /**
     * メッセージ編集の時間制限をテスト
     */
    public function test_message_edit_time_limit()
    {
        // ユーザーと条件を作成
        $buyer = User::factory()->create();
        $seller = User::factory()->create();
        $condition = Condition::factory()->create();

        // 商品を作成
        $item = Item::factory()->create(['user_id' => $seller->id, 'condition_id' => $condition->id]);

        // 商品を購入済みにする
        SoldItem::create(['user_id' => $buyer->id, 'item_id' => $item->id]);

        // 古いメッセージを作成（15分以上前）
        $oldMessage = Message::factory()->create([
            'sender_id' => $buyer->id,
            'receiver_id' => $seller->id,
            'item_id' => $item->id,
            'message' => '古いメッセージです',
            'created_at' => now()->subMinutes(16),
        ]);

        // 古いメッセージを編集しようとする
        $response = $this->actingAs($buyer)->put("/messages/{$oldMessage->id}", [
            'message' => '編集しようとしたメッセージ'
        ]);

        $response->assertStatus(403);

        // メッセージが編集されていないことを確認
        $oldMessage->refresh();
        $this->assertEquals('古いメッセージです', $oldMessage->message);
        $this->assertFalse($oldMessage->is_edited);
    }

    /**
     * 新しいメッセージは編集可能であることをテスト
     */
    public function test_recent_message_can_be_edited()
    {
        // ユーザーと条件を作成
        $buyer = User::factory()->create();
        $seller = User::factory()->create();
        $condition = Condition::factory()->create();

        // 商品を作成
        $item = Item::factory()->create(['user_id' => $seller->id, 'condition_id' => $condition->id]);

        // 商品を購入済みにする
        SoldItem::create(['user_id' => $buyer->id, 'item_id' => $item->id]);

        // 新しいメッセージを作成（5分前）
        $recentMessage = Message::factory()->create([
            'sender_id' => $buyer->id,
            'receiver_id' => $seller->id,
            'item_id' => $item->id,
            'message' => '新しいメッセージです',
            'created_at' => now()->subMinutes(5),
        ]);

        // 新しいメッセージを編集
        $response = $this->actingAs($buyer)->put("/messages/{$recentMessage->id}", [
            'message' => '編集された新しいメッセージです'
        ]);

        $response->assertRedirect("/chat/{$item->id}");

        // メッセージが編集されたことを確認
        $recentMessage->refresh();
        $this->assertEquals('編集された新しいメッセージです', $recentMessage->message);
        $this->assertTrue($recentMessage->is_edited);
        $this->assertNotNull($recentMessage->edited_at);
    }

    /**
     * 既読メッセージも編集可能であることをテスト
     */
    public function test_read_message_can_be_edited()
    {
        // ユーザーと条件を作成
        $buyer = User::factory()->create();
        $seller = User::factory()->create();
        $condition = Condition::factory()->create();

        // 商品を作成
        $item = Item::factory()->create(['user_id' => $seller->id, 'condition_id' => $condition->id]);

        // 商品を購入済みにする
        SoldItem::create(['user_id' => $buyer->id, 'item_id' => $item->id]);

        // 既読メッセージを作成
        $readMessage = Message::factory()->read()->create([
            'sender_id' => $buyer->id,
            'receiver_id' => $seller->id,
            'item_id' => $item->id,
            'message' => '既読メッセージです',
        ]);

        // 既読メッセージを編集
        $response = $this->actingAs($buyer)->put("/messages/{$readMessage->id}", [
            'message' => '編集された既読メッセージです'
        ]);

        $response->assertRedirect("/chat/{$item->id}");

        // メッセージが編集されたことを確認
        $readMessage->refresh();
        $this->assertEquals('編集された既読メッセージです', $readMessage->message);
        $this->assertTrue($readMessage->is_edited);
        $this->assertTrue($readMessage->is_read); // 既読状態は維持される
    }

    /**
     * メッセージ編集履歴が記録されることをテスト
     */
    public function test_message_edit_history_is_recorded()
    {
        // ユーザーと条件を作成
        $buyer = User::factory()->create();
        $seller = User::factory()->create();
        $condition = Condition::factory()->create();

        // 商品を作成
        $item = Item::factory()->create(['user_id' => $seller->id, 'condition_id' => $condition->id]);

        // 商品を購入済みにする
        SoldItem::create(['user_id' => $buyer->id, 'item_id' => $item->id]);

        // メッセージを作成
        $message = Message::factory()->create([
            'sender_id' => $buyer->id,
            'receiver_id' => $seller->id,
            'item_id' => $item->id,
            'message' => '最初のメッセージです',
        ]);

        $originalCreatedAt = $message->created_at;

        // 1回目の編集
        $this->actingAs($buyer)->put("/messages/{$message->id}", [
            'message' => '1回目の編集です'
        ]);

        $message->refresh();
        $this->assertEquals('1回目の編集です', $message->message);
        $this->assertTrue($message->is_edited);
        $this->assertNotNull($message->edited_at);
        $this->assertEquals($originalCreatedAt, $message->created_at); // 作成日時は変更されない

        $firstEditTime = $message->edited_at;

        // 少し時間を置いて2回目の編集
        sleep(1);
        $this->actingAs($buyer)->put("/messages/{$message->id}", [
            'message' => '2回目の編集です'
        ]);

        $message->refresh();
        $this->assertEquals('2回目の編集です', $message->message);
        $this->assertTrue($message->is_edited);
        $this->assertNotEquals($firstEditTime, $message->edited_at); // 編集時間が更新される
    }

    /**
     * 存在しないメッセージを編集しようとした場合のテスト
     */
    public function test_cannot_edit_nonexistent_message()
    {
        // ユーザーを作成
        $user = User::factory()->create();

        // 存在しないメッセージIDで編集しようとする
        $response = $this->actingAs($user)->put('/messages/99999', [
            'message' => '存在しないメッセージの編集'
        ]);

        $response->assertStatus(404);
    }

    /**
     * メッセージ編集時に特殊文字が正しく処理されることをテスト
     */
    public function test_message_edit_handles_special_characters()
    {
        // ユーザーと条件を作成
        $buyer = User::factory()->create();
        $seller = User::factory()->create();
        $condition = Condition::factory()->create();

        // 商品を作成
        $item = Item::factory()->create(['user_id' => $seller->id, 'condition_id' => $condition->id]);

        // 商品を購入済みにする
        SoldItem::create(['user_id' => $buyer->id, 'item_id' => $item->id]);

        // メッセージを作成
        $message = Message::factory()->create([
            'sender_id' => $buyer->id,
            'receiver_id' => $seller->id,
            'item_id' => $item->id,
            'message' => '元のメッセージです',
        ]);

        // 特殊文字を含むメッセージで編集
        $specialMessage = "編集されたメッセージです！\n改行テスト\n\"引用符\"テスト\n'シングルクォート'テスト\n<script>alert('XSS')</script>\n絵文字テスト😊🎉";

        $response = $this->actingAs($buyer)->put("/messages/{$message->id}", [
            'message' => $specialMessage
        ]);

        $response->assertRedirect("/chat/{$item->id}");

        // 特殊文字を含むメッセージが正しく保存されることを確認
        $message->refresh();
        $this->assertEquals($specialMessage, $message->message);
        $this->assertTrue($message->is_edited);

        // チャット画面で特殊文字が正しく表示されることを確認
        $chatResponse = $this->actingAs($buyer)->get("/chat/{$item->id}");
        $chatResponse->assertStatus(200);
        $chatResponse->assertSee('編集されたメッセージです！');
        $chatResponse->assertSee('😊🎉');
    }

    /**
     * ユーザーが自分のメッセージを削除できることをテスト
     */
    public function test_user_can_delete_their_own_message()
    {
        // ユーザーと条件を作成
        $buyer = User::factory()->create();
        $seller = User::factory()->create();
        $condition = Condition::factory()->create();

        // 商品を作成
        $item = Item::factory()->create(['user_id' => $seller->id, 'condition_id' => $condition->id]);

        // 商品を購入済みにする
        SoldItem::create(['user_id' => $buyer->id, 'item_id' => $item->id]);

        // メッセージを作成
        $message = Message::factory()->create([
            'sender_id' => $buyer->id,
            'receiver_id' => $seller->id,
            'item_id' => $item->id,
            'message' => '削除予定のメッセージです',
        ]);

        // メッセージが存在することを確認
        $this->assertDatabaseHas('messages', [
            'id' => $message->id,
            'message' => '削除予定のメッセージです',
        ]);

        // メッセージを削除
        $response = $this->actingAs($buyer)->delete("/messages/{$message->id}");

        $response->assertRedirect("/chat/{$item->id}");

        // メッセージがソフトデリートされたことを確認
        $this->assertSoftDeleted('messages', [
            'id' => $message->id,
        ]);

        // チャット画面でメッセージが表示されないことを確認
        $chatResponse = $this->actingAs($buyer)->get("/chat/{$item->id}");
        $chatResponse->assertStatus(200);
        $chatResponse->assertDontSee('削除予定のメッセージです');
    }

    /**
     * 他人のメッセージを削除できないことをテスト
     */
    public function test_user_cannot_delete_others_message()
    {
        // ユーザーと条件を作成
        $buyer = User::factory()->create();
        $seller = User::factory()->create();
        $otherUser = User::factory()->create();
        $condition = Condition::factory()->create();

        // 商品を作成
        $item = Item::factory()->create(['user_id' => $seller->id, 'condition_id' => $condition->id]);

        // 商品を購入済みにする
        SoldItem::create(['user_id' => $buyer->id, 'item_id' => $item->id]);

        // 購入者のメッセージを作成
        $message = Message::factory()->create([
            'sender_id' => $buyer->id,
            'receiver_id' => $seller->id,
            'item_id' => $item->id,
            'message' => '他人が削除しようとするメッセージ',
        ]);

        // 他のユーザーがメッセージを削除しようとする
        $response = $this->actingAs($otherUser)->delete("/messages/{$message->id}");

        $response->assertStatus(403);

        // メッセージが削除されていないことを確認
        $this->assertDatabaseHas('messages', [
            'id' => $message->id,
            'message' => '他人が削除しようとするメッセージ',
            'deleted_at' => null,
        ]);
    }

    /**
     * メッセージ削除の時間制限をテスト
     */
    public function test_message_delete_time_limit()
    {
        // ユーザーと条件を作成
        $buyer = User::factory()->create();
        $seller = User::factory()->create();
        $condition = Condition::factory()->create();

        // 商品を作成
        $item = Item::factory()->create(['user_id' => $seller->id, 'condition_id' => $condition->id]);

        // 商品を購入済みにする
        SoldItem::create(['user_id' => $buyer->id, 'item_id' => $item->id]);

        // 古いメッセージを作成（15分以上前）
        $oldMessage = Message::factory()->create([
            'sender_id' => $buyer->id,
            'receiver_id' => $seller->id,
            'item_id' => $item->id,
            'message' => '古いメッセージです',
            'created_at' => now()->subMinutes(16),
        ]);

        // 古いメッセージを削除しようとする
        $response = $this->actingAs($buyer)->delete("/messages/{$oldMessage->id}");

        $response->assertStatus(403);

        // メッセージが削除されていないことを確認
        $this->assertDatabaseHas('messages', [
            'id' => $oldMessage->id,
            'message' => '古いメッセージです',
            'deleted_at' => null,
        ]);
    }

    /**
     * 編集済みメッセージも削除可能であることをテスト
     */
    public function test_edited_message_can_be_deleted()
    {
        // ユーザーと条件を作成
        $buyer = User::factory()->create();
        $seller = User::factory()->create();
        $condition = Condition::factory()->create();

        // 商品を作成
        $item = Item::factory()->create(['user_id' => $seller->id, 'condition_id' => $condition->id]);

        // 商品を購入済みにする
        SoldItem::create(['user_id' => $buyer->id, 'item_id' => $item->id]);

        // メッセージを作成して編集
        $message = Message::factory()->create([
            'sender_id' => $buyer->id,
            'receiver_id' => $seller->id,
            'item_id' => $item->id,
            'message' => '元のメッセージです',
        ]);

        // メッセージを編集
        $this->actingAs($buyer)->put("/messages/{$message->id}", [
            'message' => '編集されたメッセージです'
        ]);

        // 編集済みメッセージを削除
        $response = $this->actingAs($buyer)->delete("/messages/{$message->id}");

        $response->assertRedirect("/chat/{$item->id}");

        // メッセージがソフトデリートされたことを確認
        $this->assertSoftDeleted('messages', [
            'id' => $message->id,
        ]);
    }

    /**
     * 削除されたメッセージがチャット画面に表示されないことをテスト
     */
    public function test_deleted_message_not_displayed_in_chat()
    {
        // ユーザーと条件を作成
        $buyer = User::factory()->create();
        $seller = User::factory()->create();
        $condition = Condition::factory()->create();

        // 商品を作成
        $item = Item::factory()->create(['user_id' => $seller->id, 'condition_id' => $condition->id]);

        // 商品を購入済みにする
        SoldItem::create(['user_id' => $buyer->id, 'item_id' => $item->id]);

        // 複数のメッセージを作成
        $message1 = Message::factory()->create([
            'sender_id' => $buyer->id,
            'receiver_id' => $seller->id,
            'item_id' => $item->id,
            'message' => '残るメッセージ1',
        ]);

        $message2 = Message::factory()->create([
            'sender_id' => $seller->id,
            'receiver_id' => $buyer->id,
            'item_id' => $item->id,
            'message' => '削除されるメッセージ',
        ]);

        $message3 = Message::factory()->create([
            'sender_id' => $buyer->id,
            'receiver_id' => $seller->id,
            'item_id' => $item->id,
            'message' => '残るメッセージ2',
        ]);

        // 2番目のメッセージを削除
        $this->actingAs($seller)->delete("/messages/{$message2->id}");

        // チャット画面にアクセス
        $response = $this->actingAs($buyer)->get("/chat/{$item->id}");

        $response->assertStatus(200);

        // 削除されていないメッセージは表示される
        $response->assertSee('残るメッセージ1');
        $response->assertSee('残るメッセージ2');

        // 削除されたメッセージは表示されない
        $response->assertDontSee('削除されるメッセージ');

        // ビューデータでも削除されたメッセージが含まれていないことを確認
        $messages = $response->viewData('messages');
        $this->assertCount(2, $messages);
        $this->assertFalse($messages->contains('id', $message2->id));
    }

    /**
     * 削除されたメッセージが未読カウントに影響しないことをテスト
     */
    public function test_deleted_message_does_not_affect_unread_count()
    {
        // ユーザーと条件を作成
        $buyer = User::factory()->create();
        $seller = User::factory()->create();
        $condition = Condition::factory()->create();

        // 2つの商品を作成
        $item1 = Item::factory()->create(['user_id' => $seller->id, 'condition_id' => $condition->id]);
        $item2 = Item::factory()->create(['user_id' => $seller->id, 'condition_id' => $condition->id]);

        // 両方の商品を購入
        SoldItem::create(['user_id' => $buyer->id, 'item_id' => $item1->id]);
        SoldItem::create(['user_id' => $buyer->id, 'item_id' => $item2->id]);

        // 商品2に未読メッセージを作成
        $unreadMessage1 = Message::factory()->unread()->create([
            'sender_id' => $seller->id,
            'receiver_id' => $buyer->id,
            'item_id' => $item2->id,
            'message' => '未読メッセージ1',
        ]);

        $unreadMessage2 = Message::factory()->unread()->create([
            'sender_id' => $seller->id,
            'receiver_id' => $buyer->id,
            'item_id' => $item2->id,
            'message' => '削除される未読メッセージ',
        ]);

        $unreadMessage3 = Message::factory()->unread()->create([
            'sender_id' => $seller->id,
            'receiver_id' => $buyer->id,
            'item_id' => $item2->id,
            'message' => '未読メッセージ3',
        ]);

        // 商品1のチャット画面にアクセスして未読カウントを確認
        $response = $this->actingAs($buyer)->get("/chat/{$item1->id}");
        $otherTransactions = $response->viewData('otherTransactions');
        $item2Transaction = $otherTransactions->firstWhere('id', $item2->id);
        $this->assertEquals(3, $item2Transaction->unread_count);

        // 2番目のメッセージを削除
        $this->actingAs($seller)->delete("/messages/{$unreadMessage2->id}");

        // 再度チャット画面にアクセスして未読カウントが減ったことを確認
        $response = $this->actingAs($buyer)->get("/chat/{$item1->id}");
        $otherTransactions = $response->viewData('otherTransactions');
        $item2Transaction = $otherTransactions->firstWhere('id', $item2->id);
        $this->assertEquals(2, $item2Transaction->unread_count);
    }

    /**
     * 存在しないメッセージを削除しようとした場合のテスト
     */
    public function test_cannot_delete_nonexistent_message()
    {
        // ユーザーを作成
        $user = User::factory()->create();

        // 存在しないメッセージIDで削除しようとする
        $response = $this->actingAs($user)->delete('/messages/99999');

        $response->assertStatus(404);
    }

    /**
     * 削除されたメッセージを再度削除しようとした場合のテスト
     */
    public function test_cannot_delete_already_deleted_message()
    {
        // ユーザーと条件を作成
        $buyer = User::factory()->create();
        $seller = User::factory()->create();
        $condition = Condition::factory()->create();

        // 商品を作成
        $item = Item::factory()->create(['user_id' => $seller->id, 'condition_id' => $condition->id]);

        // 商品を購入済みにする
        SoldItem::create(['user_id' => $buyer->id, 'item_id' => $item->id]);

        // メッセージを作成
        $message = Message::factory()->create([
            'sender_id' => $buyer->id,
            'receiver_id' => $seller->id,
            'item_id' => $item->id,
            'message' => '削除されるメッセージ',
        ]);

        // メッセージを削除
        $this->actingAs($buyer)->delete("/messages/{$message->id}");

        // 既に削除されたメッセージを再度削除しようとする
        $response = $this->actingAs($buyer)->delete("/messages/{$message->id}");

        $response->assertStatus(404);
    }
}
