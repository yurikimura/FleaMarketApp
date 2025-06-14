@extends('layouts.default')

<!-- タイトル -->
@section('title','マイページ')

<!-- css読み込み -->
@section('css')
<link rel="stylesheet" href="{{ asset('/css/index.css')  }}" >
<link rel="stylesheet" href="{{ asset('/css/mypage.css')  }}" >
@endsection

<!-- 本体 -->
@section('content')

@include('components.header')
<div class="container">
    <div class="user">
            <div class="user__info">
                <div class="user__img">
                    @if (isset($user->profile->img_url))
                        <img class="user__icon" src="{{ \Storage::url($user->profile->img_url) }}" alt="">
                    @else
                        <img id="myImage" class="user__icon" src="{{ asset('img/icon.png') }}" alt="">
                    @endif
                </div>
                <div class="user__details">
                    <p class="user__name">{{$user->name}}</p>
                    <!-- 評価表示を追加 -->
                    <div class="user__rating">
                        @if($user->getRatingCount() > 0)
                            <div class="rating-stars">
                                @php
                                    $averageRating = $user->getAverageRating();
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
                        @else
                            <div class="rating-stars">
                                @for($i = 1; $i <= 5; $i++)
                                    <span class="star empty">☆</span>
                                @endfor
                            </div>
                        @endif
                    </div>
                </div>
            </div>
            <div class="mypage__user--btn">
            <a class="btn2" href="/mypage/profile">プロフィールを編集</a>
            </div>
    </div>
    <div class="border">
        <ul class="border__list">
            <li><a href="/mypage?page=sell">出品した商品</a></li>
            <li><a href="/mypage?page=buy">購入した商品</a></li>
            <li>
                <a href="/mypage?page=trading">
                    取引中の商品
                    @if(isset($tradingItemsCount) && $tradingItemsCount > 0)
                        <span class="tab-count">{{ $tradingItemsCount }}</span>
                    @endif
                </a>
            </li>
        </ul>
    </div>
    <div class="items">
        @foreach ($items as $item)
        <div class="item">
            @if(request()->get('page') == 'buy')
                <!-- 購入した商品の場合はチャット画面へのリンク -->
                <a href="/chat/{{$item->id}}?show_rating=true">
                    @if ($item->sold())
                        <div class="item__img sold">
                            <img src="{{ \Storage::url($item->img_url) }}" alt="商品画像">
                        </div>
                    @else
                        <div class="item__img">
                            <img src="{{ \Storage::url($item->img_url) }}" alt="商品画像">
                        </div>
                    @endif
                    <p class="item__name">{{$item->name}}</p>
                </a>
            @elseif(request()->get('page') == 'trading')
                <!-- 取引中の商品の場合はチャット画面へのリンク -->
                <a href="/chat/{{$item->id}}">
                    <div class="item__img">
                        <img src="{{ \Storage::url($item->img_url) }}" alt="商品画像">
                        @if(isset($item->unread_count) && $item->unread_count > 0)
                            <div class="notification-badge">
                                {{ $item->unread_count > 9 ? '9+' : $item->unread_count }}
                            </div>
                        @endif
                    </div>
                    <p class="item__name">{{$item->name}}</p>
                </a>
            @else
                <!-- 出品した商品の場合 -->
                @if ($item->sold())
                    <!-- 売り切れアイテムはチャット画面へのリンク -->
                    <a href="/chat/{{$item->id}}">
                        <div class="item__img sold">
                            <img src="{{ \Storage::url($item->img_url) }}" alt="商品画像">
                        </div>
                        <p class="item__name">{{$item->name}}</p>
                    </a>
                @else
                    <!-- 販売中アイテムは商品詳細へのリンク -->
                    <a href="/item/{{$item->id}}">
                        <div class="item__img">
                            <img src="{{ \Storage::url($item->img_url) }}" alt="商品画像">
                        </div>
                        <p class="item__name">{{$item->name}}</p>
                    </a>
                @endif
            @endif
        </div>
        @endforeach
    </div>
</div>
@endsection
