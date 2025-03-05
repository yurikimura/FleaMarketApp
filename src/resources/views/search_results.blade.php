@extends('layouts.default')

@section('title', '検索結果')

@section('content')
    @include('components.header')
    <div class="container">
        <h1>検索結果</h1>
        <div class="items">
            @forelse ($items as $item)
                <div class="item">
                    <a href="/item/{{$item->id}}">
                        <div class="item__img">
                            <img src="{{ \Storage::url($item->img_url) }}" alt="商品画像">
                        </div>
                        <p class="item__name">{{$item->name}}</p>
                    </a>
                </div>
            @empty
                <p>該当する商品が見つかりませんでした。</p>
            @endforelse
        </div>
    </div>
@endsection
