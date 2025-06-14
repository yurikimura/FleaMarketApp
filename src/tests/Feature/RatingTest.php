<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Item;
use App\Models\Condition;
use App\Models\Rating;
use App\Models\SoldItem;

class RatingTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_rate_transaction_partner()
    {
        // ユーザーとコンディションを作成
        $seller = User::factory()->create();
        $buyer = User::factory()->create();
        $condition = Condition::factory()->create();

        // 商品を作成（売り手が出品）
        $item = Item::factory()->create([
            'user_id' => $seller->id,
            'condition_id' => $condition->id,
        ]);

        // 取引完了状態を作成
        $soldItem = SoldItem::create([
            'item_id' => $item->id,
            'user_id' => $buyer->id,
            'is_completed' => true
        ]);

        // 買い手として評価を投稿
        $this->actingAs($buyer);

        $response = $this->postJson('/rating/store', [
            'item_id' => $item->id,
            'rated_user_id' => $seller->id,
            'rating' => 5,
            'comment' => '素晴らしい取引でした！',
        ]);

        $response->assertStatus(200)
                ->assertJson([
                    'success' => '評価が完了しました',
                    'redirect_url' => '/'
                ]);

        // データベースに評価が保存されているか確認
        $this->assertDatabaseHas('ratings', [
            'rater_id' => $buyer->id,
            'rated_user_id' => $seller->id,
            'item_id' => $item->id,
            'rating' => 5,
            'comment' => '素晴らしい取引でした！',
        ]);
    }

    public function test_seller_can_rate_buyer()
    {
        // ユーザーとコンディションを作成
        $seller = User::factory()->create();
        $buyer = User::factory()->create();
        $condition = Condition::factory()->create();

        // 商品を作成（売り手が出品）
        $item = Item::factory()->create([
            'user_id' => $seller->id,
            'condition_id' => $condition->id,
        ]);

        // 取引完了状態を作成
        $soldItem = SoldItem::create([
            'item_id' => $item->id,
            'user_id' => $buyer->id,
            'is_completed' => true
        ]);

        // 売り手として評価を投稿
        $this->actingAs($seller);

        $response = $this->postJson('/rating/store', [
            'item_id' => $item->id,
            'rated_user_id' => $buyer->id,
            'rating' => 4,
            'comment' => 'スムーズな取引でした。',
        ]);

        $response->assertStatus(200)
                ->assertJson([
                    'success' => '評価が完了しました',
                    'redirect_url' => '/'
                ]);

        $this->assertDatabaseHas('ratings', [
            'rater_id' => $seller->id,
            'rated_user_id' => $buyer->id,
            'item_id' => $item->id,
            'rating' => 4,
            'comment' => 'スムーズな取引でした。',
        ]);
    }

    public function test_user_cannot_rate_themselves()
    {
        $user = User::factory()->create();
        $condition = Condition::factory()->create();

        $item = Item::factory()->create([
            'user_id' => $user->id,
            'condition_id' => $condition->id,
        ]);

        // 取引完了状態を作成
        $soldItem = SoldItem::create([
            'item_id' => $item->id,
            'user_id' => $user->id,
            'is_completed' => true
        ]);

        $this->actingAs($user);

        $response = $this->postJson('/rating/store', [
            'item_id' => $item->id,
            'rated_user_id' => $user->id,
            'rating' => 5,
            'comment' => 'テストコメント',
        ]);

        $response->assertStatus(400)
                ->assertJson(['error' => '自分自身を評価することはできません']);

        $this->assertDatabaseMissing('ratings', [
            'rater_id' => $user->id,
            'rated_user_id' => $user->id,
            'item_id' => $item->id,
        ]);
    }

    public function test_user_cannot_rate_same_transaction_twice()
    {
        $seller = User::factory()->create();
        $buyer = User::factory()->create();
        $condition = Condition::factory()->create();

        $item = Item::factory()->create([
            'user_id' => $seller->id,
            'condition_id' => $condition->id,
        ]);

        // 取引完了状態を作成
        $soldItem = SoldItem::create([
            'item_id' => $item->id,
            'user_id' => $buyer->id,
            'is_completed' => true
        ]);

        // 最初の評価を作成
        Rating::factory()->create([
            'rater_id' => $buyer->id,
            'rated_user_id' => $seller->id,
            'item_id' => $item->id,
            'rating' => 5,
        ]);

        $this->actingAs($buyer);

        // 同じ取引に対して再度評価を試行
        $response = $this->postJson('/rating/store', [
            'item_id' => $item->id,
            'rated_user_id' => $seller->id,
            'rating' => 4,
            'comment' => '2回目の評価',
        ]);

        $response->assertStatus(400)
                ->assertJson(['error' => '既に評価済みです']);

        // データベースに重複した評価がないことを確認
        $this->assertEquals(1, Rating::where('rater_id', $buyer->id)
            ->where('rated_user_id', $seller->id)
            ->where('item_id', $item->id)
            ->count());
    }

    public function test_rating_validation_requires_valid_rating_value()
    {
        $seller = User::factory()->create();
        $buyer = User::factory()->create();
        $condition = Condition::factory()->create();

        $item = Item::factory()->create([
            'user_id' => $seller->id,
            'condition_id' => $condition->id,
        ]);

        // 取引完了状態を作成
        $soldItem = SoldItem::create([
            'item_id' => $item->id,
            'user_id' => $buyer->id,
            'is_completed' => true
        ]);

        $this->actingAs($buyer);

        // 無効な評価値（0）
        $response = $this->postJson('/rating/store', [
            'item_id' => $item->id,
            'rated_user_id' => $seller->id,
            'rating' => 0,
            'comment' => 'テストコメント',
        ]);

        $response->assertStatus(422);

        // 無効な評価値（6）
        $response = $this->postJson('/rating/store', [
            'item_id' => $item->id,
            'rated_user_id' => $seller->id,
            'rating' => 6,
            'comment' => 'テストコメント',
        ]);

        $response->assertStatus(422);

        // 評価値なし
        $response = $this->postJson('/rating/store', [
            'item_id' => $item->id,
            'rated_user_id' => $seller->id,
            'comment' => 'テストコメント',
        ]);

        $response->assertStatus(422);
    }

    public function test_rating_comment_length_validation()
    {
        $seller = User::factory()->create();
        $buyer = User::factory()->create();
        $condition = Condition::factory()->create();

        $item = Item::factory()->create([
            'user_id' => $seller->id,
            'condition_id' => $condition->id,
        ]);

        // 取引完了状態を作成
        $soldItem = SoldItem::create([
            'item_id' => $item->id,
            'user_id' => $buyer->id,
            'is_completed' => true
        ]);

        $this->actingAs($buyer);

        // 長すぎるコメント（501文字）
        $longComment = str_repeat('あ', 501);

        $response = $this->postJson('/rating/store', [
            'item_id' => $item->id,
            'rated_user_id' => $seller->id,
            'rating' => 5,
            'comment' => $longComment,
        ]);

        $response->assertStatus(422);

        // 適切な長さのコメント（500文字）
        $validComment = str_repeat('あ', 500);

        $response = $this->postJson('/rating/store', [
            'item_id' => $item->id,
            'rated_user_id' => $seller->id,
            'rating' => 5,
            'comment' => $validComment,
        ]);

        $response->assertStatus(200)
                ->assertJson(['success' => '評価が完了しました']);
    }

    public function test_rating_requires_existing_item_and_user()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // 存在しない商品ID
        $response = $this->postJson('/rating/store', [
            'item_id' => 99999,
            'rated_user_id' => $user->id,
            'rating' => 5,
        ]);

        $response->assertStatus(422);

        // 存在しないユーザーID
        $condition = Condition::factory()->create();
        $item = Item::factory()->create([
            'user_id' => $user->id,
            'condition_id' => $condition->id,
        ]);

        $response = $this->postJson('/rating/store', [
            'item_id' => $item->id,
            'rated_user_id' => 99999,
            'rating' => 5,
        ]);

        $response->assertStatus(422);
    }

    public function test_complete_transaction_button_appears_for_transaction_participants()
    {
        $seller = User::factory()->create();
        $buyer = User::factory()->create();
        $condition = Condition::factory()->create();

        $item = Item::factory()->create([
            'user_id' => $seller->id,
            'condition_id' => $condition->id,
        ]);

        // 購入記録を作成（取引未完了）
        $soldItem = SoldItem::create([
            'item_id' => $item->id,
            'user_id' => $buyer->id,
            'is_completed' => false
        ]);

        // 買い手としてチャット画面にアクセス
        $this->actingAs($buyer);

        $response = $this->get("/chat/{$item->id}");

        $response->assertStatus(200);
        $response->assertSee('取引完了');
        $response->assertSee('ratingModal');
    }

    /**
     * 売り手が取引完了後に買い手を評価できることをテスト
     */
    public function test_seller_can_rate_buyer_after_transaction_completion()
    {
        // 商品を作成（売り手）
        $seller = User::factory()->create();
        $buyer = User::factory()->create();
        $item = Item::factory()->create(['user_id' => $seller->id]);

        // 購入記録を作成し、取引完了状態にする
        $soldItem = SoldItem::create([
            'item_id' => $item->id,
            'user_id' => $buyer->id,
            'is_completed' => true
        ]);

        // 売り手としてログイン
        $this->actingAs($seller);

        // 売り手が買い手を評価
        $response = $this->postJson('/rating/store', [
            'item_id' => $item->id,
            'rated_user_id' => $buyer->id,
            'rating' => 4,
            'comment' => '良い買い手でした'
        ]);

        $response->assertStatus(200)
                ->assertJson(['success' => '評価が完了しました']);

        // データベースに評価が保存されていることを確認
        $this->assertDatabaseHas('ratings', [
            'rater_id' => $seller->id,
            'rated_user_id' => $buyer->id,
            'item_id' => $item->id,
            'rating' => 4,
            'comment' => '良い買い手でした'
        ]);
    }

    /**
     * 取引が完了していない場合は評価できないことをテスト
     */
    public function test_cannot_rate_before_transaction_completion()
    {
        // 商品を作成（売り手）
        $seller = User::factory()->create();
        $buyer = User::factory()->create();
        $item = Item::factory()->create(['user_id' => $seller->id]);

        // 購入記録を作成（取引未完了）
        $soldItem = SoldItem::create([
            'item_id' => $item->id,
            'user_id' => $buyer->id,
            'is_completed' => false
        ]);

        // 売り手としてログイン
        $this->actingAs($seller);

        // 売り手が買い手を評価しようとする
        $response = $this->postJson('/rating/store', [
            'item_id' => $item->id,
            'rated_user_id' => $buyer->id,
            'rating' => 4,
            'comment' => '良い買い手でした'
        ]);

        $response->assertStatus(400)
                ->assertJson(['error' => '取引が完了していません']);

        // データベースに評価が保存されていないことを確認
        $this->assertDatabaseMissing('ratings', [
            'rater_id' => $seller->id,
            'rated_user_id' => $buyer->id,
            'item_id' => $item->id
        ]);
    }

    /**
     * 買い手が取引を完了できることをテスト
     */
    public function test_buyer_can_complete_transaction()
    {
        // 商品を作成（売り手）
        $seller = User::factory()->create();
        $buyer = User::factory()->create();
        $item = Item::factory()->create(['user_id' => $seller->id]);

        // 購入記録を作成（取引未完了）
        $soldItem = SoldItem::create([
            'item_id' => $item->id,
            'user_id' => $buyer->id,
            'is_completed' => false
        ]);

        // 買い手としてログイン
        $this->actingAs($buyer);

        // 買い手が取引を完了
        $response = $this->postJson("/transaction/{$item->id}/complete");

        $response->assertStatus(200)
                ->assertJson(['success' => '取引が完了しました']);

        // データベースで取引完了状態が更新されていることを確認
        $this->assertDatabaseHas('sold_items', [
            'item_id' => $item->id,
            'user_id' => $buyer->id,
            'is_completed' => true
        ]);
    }

    /**
     * 売り手は取引完了できないことをテスト
     */
    public function test_seller_cannot_complete_transaction()
    {
        // 商品を作成（売り手）
        $seller = User::factory()->create();
        $buyer = User::factory()->create();
        $item = Item::factory()->create(['user_id' => $seller->id]);

        // 購入記録を作成（取引未完了）
        $soldItem = SoldItem::create([
            'item_id' => $item->id,
            'user_id' => $buyer->id,
            'is_completed' => false
        ]);

        // 売り手としてログイン
        $this->actingAs($seller);

        // 売り手が取引完了しようとする
        $response = $this->postJson("/transaction/{$item->id}/complete");

        $response->assertStatus(403)
                ->assertJson(['error' => '取引完了権限がありません']);

        // データベースで取引が未完了のままであることを確認
        $this->assertDatabaseHas('sold_items', [
            'item_id' => $item->id,
            'user_id' => $buyer->id,
            'is_completed' => false
        ]);
    }

    /**
     * チャット画面で取引完了ボタンが適切に表示されることをテスト
     */
    public function test_transaction_complete_button_visibility()
    {
        // 商品を作成（売り手）
        $seller = User::factory()->create();
        $buyer = User::factory()->create();
        $condition = Condition::factory()->create();
        $item = Item::factory()->create([
            'user_id' => $seller->id,
            'condition_id' => $condition->id
        ]);

        // 購入記録を作成（取引未完了）
        $soldItem = SoldItem::create([
            'item_id' => $item->id,
            'user_id' => $buyer->id,
            'is_completed' => false
        ]);

        // 買い手としてログイン
        $this->actingAs($buyer);

        // チャット画面にアクセス
        $response = $this->get("/chat/{$item->id}");

        $response->assertStatus(200)
                ->assertSee('取引完了')
                ->assertSee('onclick="completeTransaction()"');
    }

    /**
     * 取引完了後に評価ボタンが表示されることをテスト
     */
    public function test_rating_button_appears_after_transaction_completion()
    {
        // 商品を作成（売り手）
        $seller = User::factory()->create();
        $buyer = User::factory()->create();
        $item = Item::factory()->create(['user_id' => $seller->id]);

        // 購入記録を作成（取引完了）
        $soldItem = SoldItem::create([
            'item_id' => $item->id,
            'user_id' => $buyer->id,
            'is_completed' => true
        ]);

        // 売り手としてログイン
        $this->actingAs($seller);

        // チャット画面にアクセス
        $response = $this->get("/chat/{$item->id}");

        $response->assertStatus(200)
                ->assertSee('取引相手を評価する')
                ->assertSee('onclick="openRatingModal()"');
    }

    /**
     * 評価送信後に商品一覧にリダイレクトするかテスト
     */
    public function test_rating_redirects_to_product_list_after_submission()
    {
        // ユーザーとアイテムを作成
        $seller = User::factory()->create();
        $buyer = User::factory()->create();
        $item = Item::factory()->create(['user_id' => $seller->id]);

        // 完了した取引を作成
        $soldItem = SoldItem::create([
            'user_id' => $buyer->id,
            'item_id' => $item->id,
            'is_completed' => true
        ]);

        // 売り手として評価を送信
        $response = $this->actingAs($seller)->postJson('/rating/store', [
            'item_id' => $item->id,
            'rating' => 5,
            'comment' => '良い取引でした'
        ]);

        // レスポンスが成功し、リダイレクトURLが含まれているかチェック
        $response->assertStatus(200)
                ->assertJson([
                    'success' => '評価を送信しました',
                    'redirect_url' => '/'
                ]);

        // データベースに評価が保存されているかチェック
        $this->assertDatabaseHas('ratings', [
            'rater_id' => $seller->id,
            'rated_user_id' => $buyer->id,
            'item_id' => $item->id,
            'rating' => 5,
            'comment' => '良い取引でした'
        ]);
    }

    /**
     * 買い手が評価送信後に商品一覧にリダイレクトするかテスト
     */
    public function test_buyer_rating_redirects_to_product_list_after_submission()
    {
        // ユーザーとアイテムを作成
        $seller = User::factory()->create();
        $buyer = User::factory()->create();
        $item = Item::factory()->create(['user_id' => $seller->id]);

        // 完了した取引を作成
        $soldItem = SoldItem::create([
            'user_id' => $buyer->id,
            'item_id' => $item->id,
            'is_completed' => true
        ]);

        // 買い手として評価を送信
        $response = $this->actingAs($buyer)->postJson('/rating/store', [
            'item_id' => $item->id,
            'rating' => 4,
            'comment' => 'ありがとうございました'
        ]);

        // レスポンスが成功し、リダイレクトURLが含まれているかチェック
        $response->assertStatus(200)
                ->assertJson([
                    'success' => '評価を送信しました',
                    'redirect_url' => '/'
                ]);

        // データベースに評価が保存されているかチェック
        $this->assertDatabaseHas('ratings', [
            'rater_id' => $buyer->id,
            'rated_user_id' => $seller->id,
            'item_id' => $item->id,
            'rating' => 4,
            'comment' => 'ありがとうございました'
        ]);
    }
}
