<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Item;
use App\Models\Condition;
use App\Models\SoldItem;

class PurchaseTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 商品購入後にチャット画面にリダイレクトされることをテスト
     */
    public function test_purchase_redirects_to_chat_page()
    {
        // ユーザーと商品状態を作成
        $seller = User::factory()->create();
        $buyer = User::factory()->create();
        $condition = Condition::factory()->create();

        // 商品を作成
        $item = Item::factory()->create([
            'user_id' => $seller->id,
            'condition_id' => $condition->id,
            'name' => 'テスト商品',
            'price' => 1000
        ]);

        // 購入者としてログイン
        $this->actingAs($buyer);

        // 商品を購入
        $response = $this->post("/purchase/{$item->id}");

        // チャット画面にリダイレクトされることを確認
        $response->assertRedirect("/chat/{$item->id}");
        $response->assertSessionHas('success', '商品を購入しました。出品者とのやり取りを開始できます。');

        // データベースに購入記録が保存されていることを確認
        $this->assertDatabaseHas('sold_items', [
            'user_id' => $buyer->id,
            'item_id' => $item->id
        ]);
    }

    /**
     * 既に売れた商品は購入できないことをテスト
     */
    public function test_cannot_purchase_already_sold_item()
    {
        // ユーザーと商品状態を作成
        $seller = User::factory()->create();
        $buyer1 = User::factory()->create();
        $buyer2 = User::factory()->create();
        $condition = Condition::factory()->create();

        // 商品を作成
        $item = Item::factory()->create([
            'user_id' => $seller->id,
            'condition_id' => $condition->id,
            'name' => 'テスト商品',
            'price' => 1000
        ]);

        // 最初の購入者が商品を購入
        SoldItem::create([
            'user_id' => $buyer1->id,
            'item_id' => $item->id
        ]);

        // 2番目の購入者としてログイン
        $this->actingAs($buyer2);

        // 購入ページにアクセス（PurchaseMiddlewareによりリダイレクトされる）
        $response = $this->get("/purchase/{$item->id}");

        // 商品詳細ページにリダイレクトされることを確認
        $response->assertRedirect("/item/{$item->id}");
    }

    /**
     * 購入後のチャット画面で成功メッセージが表示されることをテスト
     */
    public function test_success_message_displayed_in_chat_after_purchase()
    {
        // ユーザーと商品状態を作成
        $seller = User::factory()->create();
        $buyer = User::factory()->create();
        $condition = Condition::factory()->create();

        // 商品を作成
        $item = Item::factory()->create([
            'user_id' => $seller->id,
            'condition_id' => $condition->id,
            'name' => 'テスト商品',
            'price' => 1000
        ]);

        // 購入者としてログイン
        $this->actingAs($buyer);

        // 商品を購入
        $this->post("/purchase/{$item->id}");

        // チャット画面にアクセス
        $response = $this->get("/chat/{$item->id}");

        // 成功メッセージが表示されることを確認
        $response->assertStatus(200);
        $response->assertSee('商品を購入しました。出品者とのやり取りを開始できます。');
    }
}
