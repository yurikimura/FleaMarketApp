<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Item;
use App\Models\SoldItem;
use App\Mail\TransactionCompletedMail;
use Illuminate\Support\Facades\Mail;

class TransactionCompletionEmailTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 取引完了時に出品者にメールが送信されることをテスト
     */
    public function test_seller_receives_email_when_transaction_is_completed()
    {
        // メール送信をモック
        Mail::fake();

        // ユーザーとアイテムを作成
        $seller = User::factory()->create(['email' => 'seller@example.com']);
        $buyer = User::factory()->create(['email' => 'buyer@example.com']);
        $item = Item::factory()->create(['user_id' => $seller->id]);

        // 購入記録を作成（取引未完了）
        $soldItem = SoldItem::create([
            'item_id' => $item->id,
            'user_id' => $buyer->id,
            'is_completed' => false
        ]);

        // 買い手としてログイン
        $this->actingAs($buyer);

        // 取引完了を実行
        $response = $this->postJson("/transaction/{$item->id}/complete");

        // レスポンスが成功することを確認
        $response->assertStatus(200)
                ->assertJson(['success' => '取引が完了しました']);

        // メールが送信されたことを確認
        Mail::assertSent(TransactionCompletedMail::class, function ($mail) use ($seller) {
            return $mail->hasTo($seller->email);
        });

        // メールが1回だけ送信されたことを確認
        Mail::assertSent(TransactionCompletedMail::class, 1);
    }

    /**
     * メール内容が正しいことをテスト
     */
    public function test_transaction_completion_email_content_is_correct()
    {
        // メール送信をモック
        Mail::fake();

        // ユーザーとアイテムを作成
        $seller = User::factory()->create([
            'name' => '出品者太郎',
            'email' => 'seller@example.com'
        ]);
        $buyer = User::factory()->create([
            'name' => '購入者花子',
            'email' => 'buyer@example.com'
        ]);
        $item = Item::factory()->create([
            'user_id' => $seller->id,
            'name' => 'テスト商品',
            'price' => 1000
        ]);

        // 購入記録を作成（取引未完了）
        $soldItem = SoldItem::create([
            'item_id' => $item->id,
            'user_id' => $buyer->id,
            'is_completed' => false
        ]);

        // 買い手としてログイン
        $this->actingAs($buyer);

        // 取引完了を実行
        $response = $this->postJson("/transaction/{$item->id}/complete");

        // メールが正しい内容で送信されたことを確認
        Mail::assertSent(TransactionCompletedMail::class, function ($mail) use ($seller, $buyer, $item) {
            return $mail->hasTo($seller->email) &&
                   $mail->item->id === $item->id &&
                   $mail->buyer->id === $buyer->id &&
                   $mail->seller->id === $seller->id;
        });
    }

    /**
     * 売り手は取引完了できず、メールも送信されないことをテスト
     */
    public function test_seller_cannot_complete_transaction_and_no_email_sent()
    {
        // メール送信をモック
        Mail::fake();

        // ユーザーとアイテムを作成
        $seller = User::factory()->create(['email' => 'seller@example.com']);
        $buyer = User::factory()->create(['email' => 'buyer@example.com']);
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

        // エラーレスポンスが返されることを確認
        $response->assertStatus(403)
                ->assertJson(['error' => '取引完了権限がありません']);

        // メールが送信されていないことを確認
        Mail::assertNotSent(TransactionCompletedMail::class);

        // データベースで取引が未完了のままであることを確認
        $this->assertDatabaseHas('sold_items', [
            'item_id' => $item->id,
            'user_id' => $buyer->id,
            'is_completed' => false
        ]);
    }

    /**
     * 既に完了した取引では重複してメールが送信されないことをテスト
     */
    public function test_no_duplicate_email_for_already_completed_transaction()
    {
        // メール送信をモック
        Mail::fake();

        // ユーザーとアイテムを作成
        $seller = User::factory()->create(['email' => 'seller@example.com']);
        $buyer = User::factory()->create(['email' => 'buyer@example.com']);
        $item = Item::factory()->create(['user_id' => $seller->id]);

        // 購入記録を作成（既に取引完了）
        $soldItem = SoldItem::create([
            'item_id' => $item->id,
            'user_id' => $buyer->id,
            'is_completed' => true
        ]);

        // 買い手としてログイン
        $this->actingAs($buyer);

        // 既に完了した取引に対して再度完了を試行
        $response = $this->postJson("/transaction/{$item->id}/complete");

        // 成功レスポンスが返される（冪等性）
        $response->assertStatus(200);

        // メールが送信されていないことを確認（重複防止）
        Mail::assertNotSent(TransactionCompletedMail::class);
    }

    /**
     * メール送信エラーが発生しても取引完了は成功することをテスト
     */
    public function test_transaction_completes_even_if_email_fails()
    {
        // メール送信でエラーが発生するようにモック
        Mail::shouldReceive('to')->andThrow(new \Exception('メール送信エラー'));

        // ユーザーとアイテムを作成
        $seller = User::factory()->create(['email' => 'seller@example.com']);
        $buyer = User::factory()->create(['email' => 'buyer@example.com']);
        $item = Item::factory()->create(['user_id' => $seller->id]);

        // 購入記録を作成（取引未完了）
        $soldItem = SoldItem::create([
            'item_id' => $item->id,
            'user_id' => $buyer->id,
            'is_completed' => false
        ]);

        // 買い手としてログイン
        $this->actingAs($buyer);

        // 取引完了を実行
        $response = $this->postJson("/transaction/{$item->id}/complete");

        // メール送信エラーが発生しても取引完了は成功することを確認
        $response->assertStatus(200)
                ->assertJson(['success' => '取引が完了しました']);

        // データベースで取引が完了状態になっていることを確認
        $this->assertDatabaseHas('sold_items', [
            'item_id' => $item->id,
            'user_id' => $buyer->id,
            'is_completed' => true
        ]);
    }
}
