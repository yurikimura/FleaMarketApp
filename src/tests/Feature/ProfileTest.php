<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\Profile;
use App\Models\Item;
use App\Models\Condition;
use App\Models\Rating;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    /**
     * プロフィール画面で平均評価が正しく表示されることをテスト
     */
    public function test_profile_displays_average_rating()
    {
        // ユーザーと条件を作成
        $user = User::factory()->create();
        $rater1 = User::factory()->create();
        $rater2 = User::factory()->create();
        $rater3 = User::factory()->create();
        $condition = Condition::factory()->create();

        // プロフィールを作成
        Profile::factory()->create(['user_id' => $user->id]);

        // 商品を作成
        $item1 = Item::factory()->create(['user_id' => $user->id, 'condition_id' => $condition->id]);
        $item2 = Item::factory()->create(['user_id' => $user->id, 'condition_id' => $condition->id]);
        $item3 = Item::factory()->create(['user_id' => $user->id, 'condition_id' => $condition->id]);

        // 評価を作成（5, 4, 3の評価で平均4.0）
        Rating::create([
            'rater_id' => $rater1->id,
            'rated_user_id' => $user->id,
            'item_id' => $item1->id,
            'rating' => 5,
            'comment' => '素晴らしい取引でした'
        ]);

        Rating::create([
            'rater_id' => $rater2->id,
            'rated_user_id' => $user->id,
            'item_id' => $item2->id,
            'rating' => 4,
            'comment' => '良い取引でした'
        ]);

        Rating::create([
            'rater_id' => $rater3->id,
            'rated_user_id' => $user->id,
            'item_id' => $item3->id,
            'rating' => 3,
            'comment' => '普通の取引でした'
        ]);

        // プロフィール画面にアクセス
        $response = $this->actingAs($user)->get('/mypage/profile');

        $response->assertStatus(200);

        // 平均評価が表示されることを確認
        $response->assertSee('4.0'); // 平均評価
        $response->assertSee('(3件の評価)'); // 評価件数
        $response->assertSee('取引評価'); // セクションタイトル
    }

    /**
     * 評価がない場合の表示をテスト
     */
    public function test_profile_displays_no_rating_message_when_no_ratings_exist()
    {
        // ユーザーを作成
        $user = User::factory()->create();

        // プロフィールを作成
        Profile::factory()->create(['user_id' => $user->id]);

        // プロフィール画面にアクセス
        $response = $this->actingAs($user)->get('/mypage/profile');

        $response->assertStatus(200);

        // 評価がない場合のメッセージが表示されることを確認
        $response->assertSee('まだ評価がありません');
        $response->assertSee('取引評価'); // セクションタイトル
    }

    /**
     * 異なる評価での平均値計算をテスト
     */
    public function test_profile_displays_correct_average_rating_with_different_ratings()
    {
        // ユーザーと条件を作成
        $user = User::factory()->create();
        $rater1 = User::factory()->create();
        $rater2 = User::factory()->create();
        $condition = Condition::factory()->create();

        // プロフィールを作成
        Profile::factory()->create(['user_id' => $user->id]);

        // 商品を作成
        $item1 = Item::factory()->create(['user_id' => $user->id, 'condition_id' => $condition->id]);
        $item2 = Item::factory()->create(['user_id' => $user->id, 'condition_id' => $condition->id]);

        // 評価を作成（5と2の評価で平均3.5）
        Rating::create([
            'rater_id' => $rater1->id,
            'rated_user_id' => $user->id,
            'item_id' => $item1->id,
            'rating' => 5,
            'comment' => '最高の取引'
        ]);

        Rating::create([
            'rater_id' => $rater2->id,
            'rated_user_id' => $user->id,
            'item_id' => $item2->id,
            'rating' => 2,
            'comment' => '改善が必要'
        ]);

        // プロフィール画面にアクセス
        $response = $this->actingAs($user)->get('/mypage/profile');

        $response->assertStatus(200);

        // 平均評価が正しく表示されることを確認
        $response->assertSee('3.5'); // 平均評価
        $response->assertSee('(2件の評価)'); // 評価件数
    }

    /**
     * 星の表示が正しく行われることをテスト
     */
    public function test_profile_displays_correct_star_rating()
    {
        // ユーザーと条件を作成
        $user = User::factory()->create();
        $rater = User::factory()->create();
        $condition = Condition::factory()->create();

        // プロフィールを作成
        Profile::factory()->create(['user_id' => $user->id]);

        // 商品を作成
        $item = Item::factory()->create(['user_id' => $user->id, 'condition_id' => $condition->id]);

        // 4つ星の評価を作成
        Rating::create([
            'rater_id' => $rater->id,
            'rated_user_id' => $user->id,
            'item_id' => $item->id,
            'rating' => 4,
            'comment' => '良い取引でした'
        ]);

        // プロフィール画面にアクセス
        $response = $this->actingAs($user)->get('/mypage/profile');

        $response->assertStatus(200);

        // 星の表示が含まれることを確認
        $response->assertSee('class="star filled"', false);
        $response->assertSee('4.0'); // 平均評価
        $response->assertSee('(1件の評価)'); // 評価件数
    }

    /**
     * 評価がないユーザーの場合に評価セクションが表示されないことをテスト
     */
    public function test_profile_does_not_display_rating_section_when_no_ratings()
    {
        // ユーザーを作成
        $user = User::factory()->create();

        // プロフィールを作成
        Profile::factory()->create(['user_id' => $user->id]);

        // プロフィール画面にアクセス
        $response = $this->actingAs($user)->get('/mypage/profile');

        $response->assertStatus(200);

        // 評価関連の要素が表示されないことを確認
        $response->assertDontSee('class="star filled"', false);
        $response->assertDontSee('class="star half"', false);
        $response->assertDontSee('class="rating-average"', false);
        $response->assertDontSee('class="rating-count"', false);

        // 「まだ評価がありません」メッセージが表示されることを確認
        $response->assertSee('まだ評価がありません');
        $response->assertSee('取引評価'); // セクションタイトルは表示される
    }

    /**
     * 評価がないユーザーの場合に星や数値が表示されないことをテスト
     */
    public function test_profile_does_not_display_stars_or_numbers_when_no_ratings()
    {
        // ユーザーを作成
        $user = User::factory()->create();

        // プロフィールを作成
        Profile::factory()->create(['user_id' => $user->id]);

        // プロフィール画面にアクセス
        $response = $this->actingAs($user)->get('/mypage/profile');

        $response->assertStatus(200);

        // 星マークが表示されないことを確認
        $response->assertDontSee('★');
        $response->assertDontSee('☆');

        // 評価数値が表示されないことを確認
        $response->assertDontSee('件の評価');
        $response->assertDontSee('(0件の評価)');

        // 平均評価の数値が表示されないことを確認
        $response->assertDontSee('0.0');

        // 「まだ評価がありません」メッセージのみ表示されることを確認
        $response->assertSee('まだ評価がありません');
    }

    /**
     * 平均評価が四捨五入されることをテスト（切り上げのケース）
     */
    public function test_profile_rounds_up_average_rating_correctly()
    {
        // ユーザーと条件を作成
        $user = User::factory()->create();
        $rater1 = User::factory()->create();
        $rater2 = User::factory()->create();
        $rater3 = User::factory()->create();
        $condition = Condition::factory()->create();

        // プロフィールを作成
        Profile::factory()->create(['user_id' => $user->id]);

        // 商品を作成
        $item1 = Item::factory()->create(['user_id' => $user->id, 'condition_id' => $condition->id]);
        $item2 = Item::factory()->create(['user_id' => $user->id, 'condition_id' => $condition->id]);
        $item3 = Item::factory()->create(['user_id' => $user->id, 'condition_id' => $condition->id]);

        // 評価を作成（4, 4, 5の評価で平均4.333... → 4.3に四捨五入）
        Rating::create([
            'rater_id' => $rater1->id,
            'rated_user_id' => $user->id,
            'item_id' => $item1->id,
            'rating' => 4,
            'comment' => '良い取引でした'
        ]);

        Rating::create([
            'rater_id' => $rater2->id,
            'rated_user_id' => $user->id,
            'item_id' => $item2->id,
            'rating' => 4,
            'comment' => '良い取引でした'
        ]);

        Rating::create([
            'rater_id' => $rater3->id,
            'rated_user_id' => $user->id,
            'item_id' => $item3->id,
            'rating' => 5,
            'comment' => '素晴らしい取引でした'
        ]);

        // プロフィール画面にアクセス
        $response = $this->actingAs($user)->get('/mypage/profile');

        $response->assertStatus(200);

        // 平均評価が4.3に四捨五入されて表示されることを確認
        $response->assertSee('4.3');
        $response->assertSee('(3件の評価)');

        // 元の値（4.333...）が表示されないことを確認
        $response->assertDontSee('4.33');
        $response->assertDontSee('4.333');
    }

    /**
     * 平均評価が四捨五入されることをテスト（切り下げのケース）
     */
    public function test_profile_rounds_down_average_rating_correctly()
    {
        // ユーザーと条件を作成
        $user = User::factory()->create();
        $rater1 = User::factory()->create();
        $rater2 = User::factory()->create();
        $rater3 = User::factory()->create();
        $condition = Condition::factory()->create();

        // プロフィールを作成
        Profile::factory()->create(['user_id' => $user->id]);

        // 商品を作成
        $item1 = Item::factory()->create(['user_id' => $user->id, 'condition_id' => $condition->id]);
        $item2 = Item::factory()->create(['user_id' => $user->id, 'condition_id' => $condition->id]);
        $item3 = Item::factory()->create(['user_id' => $user->id, 'condition_id' => $condition->id]);

        // 評価を作成（3, 3, 4の評価で平均3.333... → 3.3に四捨五入）
        Rating::create([
            'rater_id' => $rater1->id,
            'rated_user_id' => $user->id,
            'item_id' => $item1->id,
            'rating' => 3,
            'comment' => '普通の取引でした'
        ]);

        Rating::create([
            'rater_id' => $rater2->id,
            'rated_user_id' => $user->id,
            'item_id' => $item2->id,
            'rating' => 3,
            'comment' => '普通の取引でした'
        ]);

        Rating::create([
            'rater_id' => $rater3->id,
            'rated_user_id' => $user->id,
            'item_id' => $item3->id,
            'rating' => 4,
            'comment' => '良い取引でした'
        ]);

        // プロフィール画面にアクセス
        $response = $this->actingAs($user)->get('/mypage/profile');

        $response->assertStatus(200);

        // 平均評価が3.3に四捨五入されて表示されることを確認
        $response->assertSee('3.3');
        $response->assertSee('(3件の評価)');

        // 元の値（3.333...）が表示されないことを確認
        $response->assertDontSee('3.33');
        $response->assertDontSee('3.333');
    }

    /**
     * 平均評価が四捨五入されることをテスト（0.5の場合の切り上げ）
     */
    public function test_profile_rounds_half_up_average_rating_correctly()
    {
        // ユーザーと条件を作成
        $user = User::factory()->create();
        $rater1 = User::factory()->create();
        $rater2 = User::factory()->create();
        $condition = Condition::factory()->create();

        // プロフィールを作成
        Profile::factory()->create(['user_id' => $user->id]);

        // 商品を作成
        $item1 = Item::factory()->create(['user_id' => $user->id, 'condition_id' => $condition->id]);
        $item2 = Item::factory()->create(['user_id' => $user->id, 'condition_id' => $condition->id]);

        // 評価を作成（3, 4の評価で平均3.5 → 3.5のまま表示）
        Rating::create([
            'rater_id' => $rater1->id,
            'rated_user_id' => $user->id,
            'item_id' => $item1->id,
            'rating' => 3,
            'comment' => '普通の取引でした'
        ]);

        Rating::create([
            'rater_id' => $rater2->id,
            'rated_user_id' => $user->id,
            'item_id' => $item2->id,
            'rating' => 4,
            'comment' => '良い取引でした'
        ]);

        // プロフィール画面にアクセス
        $response = $this->actingAs($user)->get('/mypage/profile');

        $response->assertStatus(200);

        // 平均評価が3.5で表示されることを確認
        $response->assertSee('3.5');
        $response->assertSee('(2件の評価)');
    }

    /**
     * 平均評価が整数の場合に.0が表示されることをテスト
     */
    public function test_profile_displays_integer_rating_with_decimal_point()
    {
        // ユーザーと条件を作成
        $user = User::factory()->create();
        $rater1 = User::factory()->create();
        $rater2 = User::factory()->create();
        $condition = Condition::factory()->create();

        // プロフィールを作成
        Profile::factory()->create(['user_id' => $user->id]);

        // 商品を作成
        $item1 = Item::factory()->create(['user_id' => $user->id, 'condition_id' => $condition->id]);
        $item2 = Item::factory()->create(['user_id' => $user->id, 'condition_id' => $condition->id]);

        // 評価を作成（4, 4の評価で平均4.0）
        Rating::create([
            'rater_id' => $rater1->id,
            'rated_user_id' => $user->id,
            'item_id' => $item1->id,
            'rating' => 4,
            'comment' => '良い取引でした'
        ]);

        Rating::create([
            'rater_id' => $rater2->id,
            'rated_user_id' => $user->id,
            'item_id' => $item2->id,
            'rating' => 4,
            'comment' => '良い取引でした'
        ]);

        // プロフィール画面にアクセス
        $response = $this->actingAs($user)->get('/mypage/profile');

        $response->assertStatus(200);

        // 平均評価が4.0で表示されることを確認
        $response->assertSee('4.0');
        $response->assertSee('(2件の評価)');

        // 整数のみ（4）が表示されないことを確認
        $response->assertDontSee('>4<');
    }
}
