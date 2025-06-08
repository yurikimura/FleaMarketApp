<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\Item;
use App\Models\SoldItem;
use App\Models\Condition;
use App\Models\Profile;
use App\Models\Message;
use Illuminate\Support\Facades\Storage;

class MypageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    /**
     * マイページにアクセスできることをテスト
     */
    public function test_user_can_access_mypage()
    {
        // ユーザーを作成
        $user = User::factory()->create();

        // ログイン状態でマイページにアクセス
        $response = $this->actingAs($user)->get('/mypage');

        $response->assertStatus(200);
        $response->assertViewIs('mypage');
        $response->assertViewHas('user');
        $response->assertViewHas('items');
        $response->assertViewHas('unreadMessageCount');
        $response->assertViewHas('unreadMessageCountForPurchasedItems');
    }

    /**
     * マイページで出品した商品が表示されることをテスト
     */
    public function test_user_can_view_their_listed_items()
    {
        // ユーザーと商品状態を作成
        $user = User::factory()->create();
        $condition = Condition::factory()->create();

        // ユーザーが出品した商品を作成
        $item1 = Item::factory()->create([
            'user_id' => $user->id,
            'condition_id' => $condition->id,
            'name' => '出品商品1'
        ]);
        $item2 = Item::factory()->create([
            'user_id' => $user->id,
            'condition_id' => $condition->id,
            'name' => '出品商品2'
        ]);

        // 他のユーザーの商品も作成（表示されないことを確認するため）
        $otherUser = User::factory()->create();
        Item::factory()->create([
            'user_id' => $otherUser->id,
            'condition_id' => $condition->id,
            'name' => '他人の商品'
        ]);

        // マイページにアクセス（デフォルトは出品した商品）
        $response = $this->actingAs($user)->get('/mypage');

        $response->assertStatus(200);
        $response->assertSee('出品商品1');
        $response->assertSee('出品商品2');
        $response->assertDontSee('他人の商品');
    }

    /**
     * マイページで購入した商品（取引完了済みの商品）が表示されることをテスト
     */
    public function test_user_can_view_purchased_items()
    {
        // ユーザーと商品状態を作成
        $buyer = User::factory()->create();
        $seller = User::factory()->create();
        $condition = Condition::factory()->create();

        // 商品を作成
        $completedItem1 = Item::factory()->create([
            'user_id' => $seller->id,
            'condition_id' => $condition->id,
            'name' => '取引完了済み商品1'
        ]);
        $completedItem2 = Item::factory()->create([
            'user_id' => $seller->id,
            'condition_id' => $condition->id,
            'name' => '取引完了済み商品2'
        ]);
        $tradingItem = Item::factory()->create([
            'user_id' => $seller->id,
            'condition_id' => $condition->id,
            'name' => '未完了取引商品'
        ]);

        // 購入履歴を作成（取引完了済み）
        SoldItem::create([
            'user_id' => $buyer->id,
            'item_id' => $completedItem1->id,
            'is_completed' => true
        ]);
        SoldItem::create([
            'user_id' => $buyer->id,
            'item_id' => $completedItem2->id,
            'is_completed' => true
        ]);

        // 購入履歴を作成（取引未完了）
        SoldItem::create([
            'user_id' => $buyer->id,
            'item_id' => $tradingItem->id,
            'is_completed' => false
        ]);

        // 他のユーザーが購入した商品も作成（表示されないことを確認するため）
        $otherBuyer = User::factory()->create();
        $item3 = Item::factory()->create([
            'user_id' => $seller->id,
            'condition_id' => $condition->id,
            'name' => '他人が購入した商品'
        ]);
        SoldItem::create([
            'user_id' => $otherBuyer->id,
            'item_id' => $item3->id,
            'is_completed' => true
        ]);

        // マイページの購入した商品タブにアクセス
        $response = $this->actingAs($buyer)->get('/mypage?page=buy');

        $response->assertStatus(200);
        // 取引完了済みの商品のみ表示される
        $response->assertSee('取引完了済み商品1');
        $response->assertSee('取引完了済み商品2');
        // 取引中の商品は表示されない（商品名で確認）
        $response->assertDontSee('未完了取引商品');
        // 他人の商品は表示されない
        $response->assertDontSee('他人が購入した商品');
    }

    /**
     * 購入した商品がない場合のテスト
     */
    public function test_user_with_no_purchased_items()
    {
        // ユーザーを作成
        $user = User::factory()->create();
        $seller = User::factory()->create();
        $condition = Condition::factory()->create();

        // 取引未完了の商品を作成（購入した商品タブには表示されない）
        $tradingItem = Item::factory()->create([
            'user_id' => $seller->id,
            'condition_id' => $condition->id,
            'name' => '未完了取引商品'
        ]);

        SoldItem::create([
            'user_id' => $user->id,
            'item_id' => $tradingItem->id,
            'is_completed' => false
        ]);

        // マイページの購入した商品タブにアクセス
        $response = $this->actingAs($user)->get('/mypage?page=buy');

        $response->assertStatus(200);
        // 取引完了済みの商品がないため、商品が表示されないことを確認
        $response->assertViewHas('items');
        $items = $response->viewData('items');
        $this->assertCount(0, $items);
        // 取引中の商品は表示されない
        $response->assertDontSee('未完了取引商品');
    }

    /**
     * マイページのタブ切り替えが正しく動作することをテスト
     */
    public function test_mypage_tab_switching()
    {
        // ユーザーと商品状態を作成
        $user = User::factory()->create();
        $condition = Condition::factory()->create();

        // 出品した商品を作成
        $listedItem = Item::factory()->create([
            'user_id' => $user->id,
            'condition_id' => $condition->id,
            'name' => '出品商品'
        ]);

        // 購入した商品を作成（取引完了済み）
        $seller = User::factory()->create();
        $purchasedItem = Item::factory()->create([
            'user_id' => $seller->id,
            'condition_id' => $condition->id,
            'name' => '購入商品（完了済み）'
        ]);
        SoldItem::create([
            'user_id' => $user->id,
            'item_id' => $purchasedItem->id,
            'is_completed' => true
        ]);

        // 取引中の商品を作成
        $tradingItem = Item::factory()->create([
            'user_id' => $seller->id,
            'condition_id' => $condition->id,
            'name' => '未完了取引商品'
        ]);
        SoldItem::create([
            'user_id' => $user->id,
            'item_id' => $tradingItem->id,
            'is_completed' => false
        ]);

        // 出品した商品タブ（デフォルト）
        $response = $this->actingAs($user)->get('/mypage');
        $response->assertStatus(200);
        $response->assertSee('出品商品');
        $response->assertDontSee('購入商品（完了済み）');
        $response->assertDontSee('未完了取引商品');

        // 購入した商品タブ（取引完了済みのみ）
        $response = $this->actingAs($user)->get('/mypage?page=buy');
        $response->assertStatus(200);
        $response->assertSee('購入商品（完了済み）');
        $response->assertDontSee('出品商品');
        $response->assertDontSee('未完了取引商品');

        // 取引中の商品タブ
        $response = $this->actingAs($user)->get('/mypage?page=trading');
        $response->assertStatus(200);
        $response->assertSee('未完了取引商品');
        $response->assertDontSee('出品商品');
        $response->assertDontSee('購入商品（完了済み）');
    }

    /**
     * 未認証ユーザーがマイページにアクセスできないことをテスト
     */
    public function test_unauthenticated_user_cannot_access_mypage()
    {
        // 未認証状態でマイページにアクセス
        $response = $this->get('/mypage');

        // ログインページにリダイレクトされることを確認
        $response->assertRedirect('/login');
    }

    /**
     * マイページに必要な要素が表示されることをテスト
     */
    public function test_mypage_displays_required_elements()
    {
        // ユーザーとプロフィールを作成
        $user = User::factory()->create(['name' => 'テストユーザー']);
        Profile::factory()->create(['user_id' => $user->id]);

        // マイページにアクセス
        $response = $this->actingAs($user)->get('/mypage');

        $response->assertStatus(200);
        // ユーザー名が表示されることを確認
        $response->assertSee('テストユーザー');
        // プロフィール編集リンクが表示されることを確認
        $response->assertSee('プロフィールを編集');
        // タブが表示されることを確認
        $response->assertSee('出品した商品');
        $response->assertSee('購入した商品');
    }

    /**
     * マイページで取引メッセージ数が確認できることをテスト
     */
    public function test_mypage_displays_message_counts()
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

        // 取引メッセージを作成
        Message::factory()->unread()->create([
            'sender_id' => $seller->id,
            'receiver_id' => $buyer->id,
            'item_id' => $purchasedItem->id,
            'message' => '商品を発送しました',
        ]);
        Message::factory()->unread()->create([
            'sender_id' => $seller->id,
            'receiver_id' => $buyer->id,
            'item_id' => $purchasedItem->id,
            'message' => '配送状況をお知らせします',
        ]);

        // 他の商品に関するメッセージ（購入していない商品）
        $otherItem = Item::factory()->create([
            'user_id' => $seller->id,
            'condition_id' => $condition->id,
        ]);
        Message::factory()->unread()->create([
            'sender_id' => $seller->id,
            'receiver_id' => $buyer->id,
            'item_id' => $otherItem->id,
            'message' => '他の商品について',
        ]);

        // マイページにアクセス
        $response = $this->actingAs($buyer)->get('/mypage');

        $response->assertStatus(200);
        // 全体の未読メッセージ数が正しく表示されることを確認
        $response->assertViewHas('unreadMessageCount', 3);
        // 取引中の商品に関する未読メッセージ数が正しく表示されることを確認
        $response->assertViewHas('unreadMessageCountForPurchasedItems', 2);
    }

    /**
     * メッセージがない場合のテスト
     */
    public function test_mypage_shows_zero_message_count_when_no_messages()
    {
        // ユーザーを作成
        $user = User::factory()->create();

        // マイページにアクセス
        $response = $this->actingAs($user)->get('/mypage');

        $response->assertStatus(200);
        // メッセージ数が0であることを確認
        $response->assertViewHas('unreadMessageCount', 0);
        $response->assertViewHas('unreadMessageCountForPurchasedItems', 0);
    }

    /**
     * マイページの購入した商品をクリックして取引チャット画面へ遷移できることをテスト
     */
    public function test_user_can_navigate_to_chat_from_purchased_items()
    {
        // ユーザーを作成
        $buyer = User::factory()->create();
        $seller = User::factory()->create();
        $condition = Condition::factory()->create();

        // 購入した商品を作成（取引完了済み）
        $purchasedItem = Item::factory()->create([
            'user_id' => $seller->id,
            'condition_id' => $condition->id,
            'name' => '取引完了済みの商品',
        ]);

        // 購入履歴を作成（取引完了済み）
        SoldItem::create([
            'user_id' => $buyer->id,
            'item_id' => $purchasedItem->id,
            'is_completed' => true
        ]);

        // マイページの購入した商品タブにアクセス
        $response = $this->actingAs($buyer)->get('/mypage?page=buy');

        $response->assertStatus(200);
        // チャット画面へのリンクが存在することを確認
        $response->assertSee("/chat/{$purchasedItem->id}");

        // 実際にチャット画面にアクセスできることを確認
        $chatResponse = $this->actingAs($buyer)->get("/chat/{$purchasedItem->id}");
        $chatResponse->assertStatus(200);
        $chatResponse->assertViewIs('chat');
        $chatResponse->assertViewHas('item');
        $chatResponse->assertViewHas('messages');
        $chatResponse->assertViewHas('user');
    }

    /**
     * 購入していない商品のチャット画面にアクセスできないことをテスト
     */
    public function test_user_cannot_access_chat_for_non_purchased_items()
    {
        // ユーザーを作成
        $user = User::factory()->create();
        $seller = User::factory()->create();
        $condition = Condition::factory()->create();

        // 他人の商品を作成（購入していない）
        $item = Item::factory()->create([
            'user_id' => $seller->id,
            'condition_id' => $condition->id,
        ]);

        // チャット画面にアクセスを試行
        $response = $this->actingAs($user)->get("/chat/{$item->id}");

        // 403エラーが返されることを確認
        $response->assertStatus(403);
    }

    /**
     * 出品者が自分の商品のチャット画面にアクセスできることをテスト
     */
    public function test_seller_can_access_chat_for_their_sold_items()
    {
        // ユーザーを作成
        $seller = User::factory()->create();
        $buyer = User::factory()->create();
        $condition = Condition::factory()->create();

        // 出品者の商品を作成
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
        $response->assertViewHas('item');
        $response->assertViewHas('messages');
        $response->assertViewHas('user');
    }

    /**
     * チャット画面でメッセージが正しく表示されることをテスト
     */
    public function test_chat_displays_messages_correctly()
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

        // メッセージを作成
        $message1 = Message::factory()->create([
            'sender_id' => $buyer->id,
            'receiver_id' => $seller->id,
            'item_id' => $item->id,
            'message' => '商品について質問があります',
            'is_read' => false,
        ]);

        $message2 = Message::factory()->create([
            'sender_id' => $seller->id,
            'receiver_id' => $buyer->id,
            'item_id' => $item->id,
            'message' => 'お答えします',
            'is_read' => false,
        ]);

        // 購入者がチャット画面にアクセス
        $response = $this->actingAs($buyer)->get("/chat/{$item->id}");

        $response->assertStatus(200);
        $response->assertSee('商品について質問があります');
        $response->assertSee('お答えします');

        // 購入者が受信したメッセージが既読になることを確認
        $message2->refresh();
        $this->assertTrue($message2->is_read);
    }

    /**
     * チャット画面からメッセージを送信できることをテスト
     */
    public function test_user_can_send_message_from_chat()
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
            'message' => 'こんにちは、商品の状態はいかがですか？'
        ]);

        // チャット画面にリダイレクトされることを確認
        $response->assertRedirect("/chat/{$item->id}");

        // メッセージがデータベースに保存されることを確認
        $this->assertDatabaseHas('messages', [
            'sender_id' => $buyer->id,
            'receiver_id' => $seller->id,
            'item_id' => $item->id,
            'message' => 'こんにちは、商品の状態はいかがですか？',
            'is_read' => false,
        ]);
    }

    /**
     * 空のメッセージを送信できないことをテスト
     */
    public function test_user_cannot_send_empty_message()
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

        // バリデーションエラーが発生することを確認
        $response->assertSessionHasErrors('message');
    }

    /**
     * マイページの出品した商品は商品詳細画面へのリンクであることをテスト
     */
    public function test_listed_items_link_to_item_detail()
    {
        // ユーザーと商品状態を作成
        $user = User::factory()->create();
        $condition = Condition::factory()->create();

        // ユーザーが出品した商品を作成
        $item = Item::factory()->create([
            'user_id' => $user->id,
            'condition_id' => $condition->id,
            'name' => '出品商品'
        ]);

        // マイページにアクセス（デフォルトは出品した商品）
        $response = $this->actingAs($user)->get('/mypage');

        $response->assertStatus(200);
        // 商品詳細画面へのリンクが存在することを確認
        $response->assertSee("/item/{$item->id}");
        // チャット画面へのリンクは存在しないことを確認
        $response->assertDontSee("/chat/{$item->id}");
    }

    /**
     * マイページで取引中の商品が表示されることをテスト
     */
    public function test_user_can_view_trading_items()
    {
        // ユーザーと商品状態を作成
        $buyer = User::factory()->create();
        $seller = User::factory()->create();
        $anotherSeller = User::factory()->create();
        $condition = Condition::factory()->create();

        // 購入者として取引中の商品を作成
        $purchasedItem = Item::factory()->create([
            'user_id' => $seller->id,
            'condition_id' => $condition->id,
            'name' => '購入した取引中商品'
        ]);

        // 出品者として取引中の商品を作成
        $soldItem = Item::factory()->create([
            'user_id' => $buyer->id,
            'condition_id' => $condition->id,
            'name' => '出品した取引中商品'
        ]);

        // 購入履歴を作成（取引未完了）
        SoldItem::create([
            'user_id' => $buyer->id,
            'item_id' => $purchasedItem->id,
            'is_completed' => false
        ]);

        SoldItem::create([
            'user_id' => $anotherSeller->id,
            'item_id' => $soldItem->id,
            'is_completed' => false
        ]);

        // 取引完了済みの商品も作成（表示されないことを確認するため）
        $completedItem = Item::factory()->create([
            'user_id' => $seller->id,
            'condition_id' => $condition->id,
            'name' => '取引完了済み商品'
        ]);

        SoldItem::create([
            'user_id' => $buyer->id,
            'item_id' => $completedItem->id,
            'is_completed' => true
        ]);

        // マイページの取引中の商品タブにアクセス
        $response = $this->actingAs($buyer)->get('/mypage?page=trading');

        $response->assertStatus(200);
        $response->assertSee('購入した取引中商品');
        $response->assertSee('出品した取引中商品');
        $response->assertDontSee('取引完了済み商品');
    }

    /**
     * 取引中の商品タブに商品数が表示されることをテスト
     */
    public function test_trading_tab_displays_item_count()
    {
        // ユーザーと商品状態を作成
        $buyer = User::factory()->create();
        $seller = User::factory()->create();
        $anotherSeller = User::factory()->create();
        $condition = Condition::factory()->create();

        // 購入者として取引中の商品を2つ作成
        $purchasedItem1 = Item::factory()->create([
            'user_id' => $seller->id,
            'condition_id' => $condition->id,
        ]);
        $purchasedItem2 = Item::factory()->create([
            'user_id' => $seller->id,
            'condition_id' => $condition->id,
        ]);

        // 出品者として取引中の商品を1つ作成
        $soldItem = Item::factory()->create([
            'user_id' => $buyer->id,
            'condition_id' => $condition->id,
        ]);

        // 購入履歴を作成（取引未完了）
        SoldItem::create([
            'user_id' => $buyer->id,
            'item_id' => $purchasedItem1->id,
            'is_completed' => false
        ]);
        SoldItem::create([
            'user_id' => $buyer->id,
            'item_id' => $purchasedItem2->id,
            'is_completed' => false
        ]);
        SoldItem::create([
            'user_id' => $anotherSeller->id,
            'item_id' => $soldItem->id,
            'is_completed' => false
        ]);

        // マイページにアクセス
        $response = $this->actingAs($buyer)->get('/mypage');

        $response->assertStatus(200);
        // 取引中の商品数（購入者として2つ + 出品者として1つ = 3つ）が表示されることを確認
        $response->assertSee('取引中の商品');
        $response->assertSee('3'); // タブに表示される数字
    }

    /**
     * 取引中の商品からチャット画面に遷移できることをテスト
     */
    public function test_user_can_navigate_to_chat_from_trading_items()
    {
        // ユーザーと商品状態を作成
        $buyer = User::factory()->create();
        $seller = User::factory()->create();
        $condition = Condition::factory()->create();

        // 取引中の商品を作成
        $item = Item::factory()->create([
            'user_id' => $seller->id,
            'condition_id' => $condition->id,
            'name' => '未完了取引商品'
        ]);

        // 購入履歴を作成（取引未完了）
        SoldItem::create([
            'user_id' => $buyer->id,
            'item_id' => $item->id,
            'is_completed' => false
        ]);

        // マイページの取引中の商品タブにアクセス
        $response = $this->actingAs($buyer)->get('/mypage?page=trading');

        $response->assertStatus(200);
        // チャット画面へのリンクが存在することを確認
        $response->assertSee("/chat/{$item->id}");
    }

    /**
     * 出品者側でも取引中の商品に未読メッセージ数が表示されることをテスト
     */
    public function test_seller_can_see_unread_message_count_on_trading_items()
    {
        // ユーザーと商品状態を作成
        $seller = User::factory()->create();
        $buyer = User::factory()->create();
        $condition = Condition::factory()->create();

        // 出品者の商品を作成
        $item = Item::factory()->create([
            'user_id' => $seller->id,
            'condition_id' => $condition->id,
            'name' => '出品者の取引中商品'
        ]);

        // 購入履歴を作成（取引未完了）
        SoldItem::create([
            'user_id' => $buyer->id,
            'item_id' => $item->id,
            'is_completed' => false
        ]);

        // 購入者から出品者への未読メッセージを作成
        Message::factory()->unread()->create([
            'sender_id' => $buyer->id,
            'receiver_id' => $seller->id,
            'item_id' => $item->id,
            'message' => '商品について質問があります',
        ]);

        Message::factory()->unread()->create([
            'sender_id' => $buyer->id,
            'receiver_id' => $seller->id,
            'item_id' => $item->id,
            'message' => '追加の質問です',
        ]);

        // 出品者のマイページの取引中の商品タブにアクセス
        $response = $this->actingAs($seller)->get('/mypage?page=trading');

        $response->assertStatus(200);
        $response->assertSee('出品者の取引中商品');

        // 未読メッセージ数が2件表示されることを確認
        $response->assertSee('notification-badge');
        $response->assertSee('2');
    }

    /**
     * 購入者側でも取引中の商品に未読メッセージ数が表示されることをテスト
     */
    public function test_buyer_can_see_unread_message_count_on_trading_items()
    {
        // ユーザーと商品状態を作成
        $buyer = User::factory()->create();
        $seller = User::factory()->create();
        $condition = Condition::factory()->create();

        // 商品を作成
        $item = Item::factory()->create([
            'user_id' => $seller->id,
            'condition_id' => $condition->id,
            'name' => '購入者の取引中商品'
        ]);

        // 購入履歴を作成（取引未完了）
        SoldItem::create([
            'user_id' => $buyer->id,
            'item_id' => $item->id,
            'is_completed' => false
        ]);

        // 出品者から購入者への未読メッセージを作成
        Message::factory()->unread()->create([
            'sender_id' => $seller->id,
            'receiver_id' => $buyer->id,
            'item_id' => $item->id,
            'message' => '商品を発送しました',
        ]);

        Message::factory()->unread()->create([
            'sender_id' => $seller->id,
            'receiver_id' => $buyer->id,
            'item_id' => $item->id,
            'message' => '配送状況をお知らせします',
        ]);

        Message::factory()->unread()->create([
            'sender_id' => $seller->id,
            'receiver_id' => $buyer->id,
            'item_id' => $item->id,
            'message' => '到着予定日について',
        ]);

        // 購入者のマイページの取引中の商品タブにアクセス
        $response = $this->actingAs($buyer)->get('/mypage?page=trading');

        $response->assertStatus(200);
        $response->assertSee('購入者の取引中商品');

        // 未読メッセージ数が3件表示されることを確認
        $response->assertSee('notification-badge');
        $response->assertSee('3');
    }

    /**
     * 取引完了前後で商品の表示タブが変わることをテスト
     */
    public function test_item_moves_from_trading_to_purchased_after_completion()
    {
        // ユーザーと商品状態を作成
        $buyer = User::factory()->create();
        $seller = User::factory()->create();
        $condition = Condition::factory()->create();

        // 商品を作成
        $item = Item::factory()->create([
            'user_id' => $seller->id,
            'condition_id' => $condition->id,
            'name' => '取引テスト商品'
        ]);

        // 購入履歴を作成（取引未完了）
        $soldItem = SoldItem::create([
            'user_id' => $buyer->id,
            'item_id' => $item->id,
            'is_completed' => false
        ]);

        // 取引完了前：取引中の商品タブに表示され、購入した商品タブには表示されない
        $tradingResponse = $this->actingAs($buyer)->get('/mypage?page=trading');
        $tradingResponse->assertStatus(200);
        $tradingResponse->assertSee('取引テスト商品');

        $purchasedResponse = $this->actingAs($buyer)->get('/mypage?page=buy');
        $purchasedResponse->assertStatus(200);
        $purchasedResponse->assertDontSee('取引テスト商品');

        // 取引を完了させる
        $soldItem->update(['is_completed' => true]);

        // 取引完了後：購入した商品タブに表示され、取引中の商品タブには表示されない
        $tradingResponseAfter = $this->actingAs($buyer)->get('/mypage?page=trading');
        $tradingResponseAfter->assertStatus(200);
        $tradingResponseAfter->assertDontSee('取引テスト商品');

        $purchasedResponseAfter = $this->actingAs($buyer)->get('/mypage?page=buy');
        $purchasedResponseAfter->assertStatus(200);
        $purchasedResponseAfter->assertSee('取引テスト商品');
    }
}

