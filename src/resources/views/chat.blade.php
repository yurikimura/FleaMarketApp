@extends('layouts.default')

@section('title', '取引チャット')

@section('css')
<link rel="stylesheet" href="{{ asset('/css/chat.css') }}">
@endsection

@section('content')
@include('components.header')

<div class="container">
    <!-- 成功メッセージの表示 -->
    @if(session('success'))
        <div class="alert alert-success">
            {{ session('success') }}
        </div>
    @endif

    <div class="chat-layout">
        <!-- サイドバー -->
        <div class="chat__sidebar">
            <h3 class="sidebar__title">取引中の商品</h3>
            <div class="sidebar__transactions">
                @if($otherTransactions->count() > 0)
                    @foreach($otherTransactions as $transaction)
                        <div class="transaction-item">
                            <a href="/chat/{{ $transaction->id }}" class="transaction-link">
                                <div class="transaction__img">
                                    <img src="{{ \Storage::url($transaction->img_url) }}" alt="商品画像">
                                    @if($transaction->unread_count > 0)
                                        <span class="notification-badge">
                                            {{ $transaction->unread_count > 9 ? '9+' : $transaction->unread_count }}
                                        </span>
                                    @endif
                                </div>
                                <div class="transaction__info">
                                    <p class="transaction__name">{{ $transaction->name }}</p>
                                    <p class="transaction__party">
                                        {{ $transaction->transaction_type === 'purchased' ? '出品者' : '購入者' }}:
                                        {{ $transaction->other_party }}
                                    </p>
                                </div>
                            </a>
                        </div>
                    @endforeach
                @else
                    <p class="no-transactions">他の取引はありません</p>
                @endif
            </div>
        </div>

        <!-- メインチャットエリア -->
        <div class="chat__main">
            @php
                $soldItem = $item->soldItem;
                $isBuyer = $soldItem && $soldItem->user_id === $user->id;
                $isSeller = $item->user_id === $user->id;

                // 取引相手の情報を取得
                if ($isBuyer) {
                    $partner = $item->user; // 出品者
                } elseif ($isSeller) {
                    $partner = $soldItem->user; // 購入者
                } else {
                    $partner = null;
                }
            @endphp

            <!-- 取引相手の名前 -->
            @if($partner)
            <div class="chat__partner-info">
                <h2 class="partner__name">{{ $partner->name }}</h2>
            </div>
            @endif

            <!-- 商品情報 -->
            <div class="chat__item-info">
                <div class="item__img">
                    <img src="{{ \Storage::url($item->img_url) }}" alt="商品画像">
                </div>
                <div class="item__details">
                    <h3 class="item__name">{{ $item->name }}</h3>
                    <p class="item__price">¥ {{ number_format($item->price) }}</p>
                </div>

                <!-- 取引完了・評価ボタン -->
                @php
                    $isCompleted = $soldItem && $soldItem->is_completed;

                    // 取引相手のIDを取得
                    $partnerId = null;
                    if ($isBuyer) {
                        $partnerId = $item->user_id; // 出品者
                    } elseif ($isSeller) {
                        $partnerId = $soldItem->user_id; // 購入者
                    }

                    // 評価済みかチェック
                    $hasRated = false;
                    if ($partnerId && $isCompleted) {
                        $hasRated = \App\Models\Rating::where('rater_id', $user->id)
                                                     ->where('rated_user_id', $partnerId)
                                                     ->where('item_id', $item->id)
                                                     ->exists();
                    }
                @endphp

                <div class="transaction-actions">
                    @if($isBuyer && !$isCompleted)
                        <!-- 購入者：取引完了ボタン -->
                        <button class="btn-complete" onclick="completeTransaction()">取引完了</button>
                    @elseif($isCompleted && !$hasRated)
                        <!-- 取引完了後：評価ボタン -->
                        <button class="btn-complete" onclick="openRatingModal()">取引相手を評価する</button>
                    @elseif($isCompleted && $hasRated)
                        <!-- 評価済み -->
                        <span class="status-completed">評価済み</span>
                    @elseif($isCompleted)
                        <!-- 取引完了済み -->
                        <span class="status-completed">取引完了済み</span>
                    @endif
                </div>
            </div>

            <!-- メッセージエリア -->
            <div class="chat__messages">
                @if($messages->count() > 0)
                    @foreach($messages as $message)
                        <div class="message {{ $message->sender_id === $user->id ? 'message--sent' : 'message--received' }}">
                            <div class="message__content">
                                <p class="message__text">{{ $message->message }}</p>
                                <span class="message__time">{{ $message->created_at->format('Y/m/d H:i') }}</span>
                                @if($message->is_edited)
                                    <span class="message__edited">(編集済み)</span>
                                @endif
                            </div>
                            @if($message->sender_id === $user->id && $message->created_at->diffInMinutes(now()) <= 15)
                                <div class="message__actions">
                                    <button class="btn-edit" onclick="editMessage({{ $message->id }}, '{{ addslashes($message->message) }}')">編集</button>
                                    <form method="POST" action="{{ route('message.delete', $message->id) }}" style="display: inline;" onsubmit="return confirm('本当に削除しますか？')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn-delete">削除</button>
                                    </form>
                                </div>
                            @endif
                        </div>
                    @endforeach
                @else
                    <p class="no-messages">まだメッセージがありません</p>
                @endif
            </div>

            <!-- メッセージ入力フォーム -->
            <div class="chat__input">
                <form action="{{ route('chat.message.send', $item->id) }}" method="POST" class="message-form">
                    @csrf
                    <div class="input-container">
                        <textarea name="message" placeholder="メッセージを入力してください" class="message-input" required></textarea>
                        <button type="submit" class="btn-send">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M2 21L23 12L2 3V10L17 12L2 14V21Z" fill="currentColor"/>
                            </svg>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- 評価モーダル -->
<div id="ratingModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>取引相手を評価</h3>
            <span class="close" onclick="closeRatingModal()">&times;</span>
        </div>
        <div class="modal-body">
            <p>取引相手を評価してください</p>
            <form id="ratingForm">
                @csrf
                <input type="hidden" name="item_id" value="{{ $item->id }}">
                <input type="hidden" name="rated_user_id" value="{{ $partnerId }}">

                <div class="rating-input">
                    <label>評価:</label>
                    <div class="star-rating">
                        <input type="radio" name="rating" value="5" id="star5" required>
                        <label for="star5" class="star">★</label>
                        <input type="radio" name="rating" value="4" id="star4">
                        <label for="star4" class="star">★</label>
                        <input type="radio" name="rating" value="3" id="star3">
                        <label for="star3" class="star">★</label>
                        <input type="radio" name="rating" value="2" id="star2">
                        <label for="star2" class="star">★</label>
                        <input type="radio" name="rating" value="1" id="star1">
                        <label for="star1" class="star">★</label>
                    </div>
                </div>
                <div class="comment-input">
                    <label for="comment">コメント:</label>
                    <textarea name="comment" id="comment" placeholder="取引の感想をお聞かせください（任意）" rows="4"></textarea>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn-cancel" onclick="closeRatingModal()">キャンセル</button>
                    <button type="submit" class="btn-submit">評価を送信</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// 取引完了処理
function completeTransaction() {
    if (confirm('取引を完了しますか？')) {
        fetch(`/transaction/{{ $item->id }}/complete`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(data.success);
                location.reload();
            } else {
                alert(data.error || '取引完了に失敗しました');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('取引完了に失敗しました');
        });
    }
}

// 評価モーダル
function openRatingModal() {
    document.getElementById('ratingModal').style.display = 'block';
}

function closeRatingModal() {
    document.getElementById('ratingModal').style.display = 'none';
}

// モーダル外をクリックしたら閉じる
window.onclick = function(event) {
    const ratingModal = document.getElementById('ratingModal');
    if (event.target == ratingModal) {
        ratingModal.style.display = 'none';
    }
}

// 星評価の処理
document.querySelectorAll('.star-rating input').forEach(input => {
    input.addEventListener('change', function() {
        const rating = this.value;
        const stars = document.querySelectorAll('.star-rating .star');
        stars.forEach((star, index) => {
            if (index >= 5 - rating) {
                star.classList.add('selected');
            } else {
                star.classList.remove('selected');
            }
        });
    });
});

// 評価フォーム送信
document.getElementById('ratingForm').addEventListener('submit', function(e) {
    e.preventDefault();

    const formData = new FormData(this);
    const data = Object.fromEntries(formData);

    fetch('/rating/store', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.success);
            closeRatingModal();
            // 商品一覧に遷移
            if (data.redirect_url) {
                window.location.href = data.redirect_url;
            } else {
                window.location.href = '/';
            }
        } else {
            alert(data.error || '評価の送信に失敗しました');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('評価の送信に失敗しました');
    });
});

function editMessage(messageId, currentMessage) {
    const newMessage = prompt('メッセージを編集してください:', currentMessage);
    if (newMessage !== null && newMessage.trim() !== '') {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = `/messages/${messageId}`;

        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        const csrfInput = document.createElement('input');
        csrfInput.type = 'hidden';
        csrfInput.name = '_token';
        csrfInput.value = csrfToken;

        const methodInput = document.createElement('input');
        methodInput.type = 'hidden';
        methodInput.name = '_method';
        methodInput.value = 'PUT';

        const messageInput = document.createElement('input');
        messageInput.type = 'hidden';
        messageInput.name = 'message';
        messageInput.value = newMessage;

        form.appendChild(csrfInput);
        form.appendChild(methodInput);
        form.appendChild(messageInput);

        document.body.appendChild(form);
        form.submit();
    }
}
</script>

@endsection
