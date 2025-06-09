@extends('layouts.default')

<!-- タイトル -->
@section('title','プロフィール設定')

<!-- css読み込み -->
@section('css')
<link rel="stylesheet" href="{{ asset('/css/profile.css')  }}" >
@endsection

<!-- 本体 -->
@section('content')

@include('components.header')

<!-- メッセージ表示 -->
@if(session('message'))
    <div class="alert alert-info" style="background-color: #d1ecf1; color: #0c5460; padding: 15px; margin: 20px auto; max-width: 600px; border-radius: 5px; text-align: center;">
        {{ session('message') }}
    </div>
@endif

<form action="/mypage/profile" method="post" class="profile center" enctype="multipart/form-data">
    @csrf
    <h1 class="page__title">プロフィール設定</h1>

    <!-- 新規ユーザー向けの案内メッセージ -->
    @if(!$profile || !$profile->postcode || !$profile->address)
        <div class="welcome-message" style="background-color: #fff3cd; color: #856404; padding: 15px; margin-bottom: 20px; border-radius: 5px; border: 1px solid #ffeaa7;">
            <h3 style="margin-bottom: 10px;">ようこそ！プロフィール設定を完了しましょう</h3>
            <p>商品を購入する際に配送先情報が必要になります。下記の情報をご入力ください。</p>
        </div>
    @endif

    <div class="user">
        <div class="user__img">
            @if (isset($profile->img_url))
                <img class="user__icon" src="{{ \Storage::url($profile->img_url) }}" alt="">
            @else
                <img id="myImage" class="user__icon" src="{{ asset('img/icon.png') }}" alt="">
            @endif
        </div>
        <div class="profile__user--btn">
            <label class="btn2">
                画像を選択する
                <input id="target" class="btn2--input" type="file" name="img_url" accept="image/png, image/jpeg">
            </label>
        </div>
    </div>
    <label for="name" class="entry__name">ユーザ名</label>
    <input name="name" id="name" type="text" class="input" value="{{ Auth::user()->name }}">
    <div class="form__error">
        @error('name')
            {{ $message }}
        @enderror
    </div>

    <!-- 平均評価表示セクション -->
    <div class="rating-section">
        <label class="entry__name">取引評価</label>
        <div class="rating-display">
            @if(Auth::user()->getRatingCount() > 0)
                <div class="rating-stars">
                    @php
                        $averageRating = Auth::user()->getAverageRating();
                        $fullStars = floor($averageRating);
                        $hasHalfStar = ($averageRating - $fullStars) >= 0.5;
                    @endphp

                    @for($i = 1; $i <= 5; $i++)
                        @if($i <= $fullStars)
                            <span class="star filled">★</span>
                        @elseif($i == $fullStars + 1 && $hasHalfStar)
                            <span class="star half">☆</span>
                        @else
                            <span class="star empty">☆</span>
                        @endif
                    @endfor
                </div>
                <div class="rating-info">
                    <span class="rating-average">{{ number_format(Auth::user()->getAverageRating(), 1) }}</span>
                    <span class="rating-count">({{ Auth::user()->getRatingCount() }}件の評価)</span>
                </div>
            @else
                <div class="no-rating">
                    <span class="rating-text">まだ評価がありません</span>
                </div>
            @endif
        </div>
    </div>

    <label for="postcode" class="entry__name">郵便番号</label>
    <input name="postcode" id="postcode" type="text" class="input" value="{{ $profile ? $profile->postcode : '' }}" size="10" maxlength="8" onKeyUp="AjaxZip3.zip2addr(this,'','address','address');">
    <div class="form__error">
        @error('postcode')
            {{ $message }}
        @enderror
    </div>

    <label for="address" class="entry__name">住所</label>
    <input name="address" id="address" type="text" class="input" value="{{ $profile ? $profile->address : '' }}">
    <div class="form__error">
        @error('address')
            {{ $message }}
        @enderror
    </div>

    <label for="building" class="entry__name">建物名</label>
    <input name="building" id="building" type="text" class="input" value="{{ $profile ? $profile->building : '' }}">
    <button class="btn btn--big">更新する</button>
</form>
<script>
const target = document.getElementById('target');
target.addEventListener('change', function (e) {
    const file = e.target.files[0]
    const reader = new FileReader();
    reader.onload = function (e) {
        const img = document.getElementById("myImage");
        console.log(img.src);
        img.src = e.target.result;
        console.log(img.src);
    }
    reader.readAsDataURL(file);
}, false);
</script>
@endsection
