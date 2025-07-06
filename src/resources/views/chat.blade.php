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
            <h3 class="sidebar__title">その他の取引</h3>
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

                $isCompleted = $soldItem && $soldItem->is_completed;

                // 評価済みかチェック
                $hasRated = false;
                $canRate = false;
                $partnerId = null;
                if ($isCompleted) {
                    if ($soldItem && $soldItem->user_id === $user->id) {
                        // 購入者の場合、出品者を評価対象とする
                        $partnerId = $item->user_id;
                        $canRate = true;
                    } elseif ($item->user_id === $user->id) {
                        // 出品者の場合、購入者を評価対象とする
                        $partnerId = $soldItem->user_id;
                        $canRate = true;
                    }

                    if ($partnerId) {
                        $hasRated = \App\Models\Rating::where('rater_id', $user->id)
                                                     ->where('rated_user_id', $partnerId)
                                                     ->where('item_id', $item->id)
                                                     ->exists();
                    }
                }
            @endphp

            <!-- 取引相手の名前 -->
            @if($partner)
            <div class="chat__partner-info" style="display: flex; justify-content: space-between; align-items: center;">
                <h2 class="partner__name">「{{ $partner->name }}」さんとの取引画面</h2>

                <!-- 取引完了・評価ボタン -->
                <div class="transaction-actions">
                    @if($isBuyer && !$isCompleted)
                        <!-- 購入者：取引完了ボタン -->
                        <button class="btn-complete" onclick="openCompleteModal()">取引を完了する</button>
                    @elseif($isBuyer && $isCompleted && !$hasRated)
                        <!-- 購入者：評価ボタン（取引完了後） -->
                        <button class="btn-complete" onclick="openRatingModal()">取引相手を評価する</button>
                    @elseif(!$isCompleted && $isSeller && !$hasRated)
                        <!-- 販売者：評価ボタン -->
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
            </div>

            <!-- メッセージエリア -->
            <div class="chat__messages">
                @if($messages->count() > 0)
                    @foreach($messages as $message)
                        <div class="message {{ $message->sender_id === $user->id ? 'message--sent' : 'message--received' }}">
                            <!-- ユーザー情報 -->
                            <div class="message__user-info">
                                <div class="message__user-avatar">
                                    @if($message->sender->profile && $message->sender->profile->img_url)
                                        <img src="{{ \Storage::url($message->sender->profile->img_url) }}" alt="ユーザーアイコン">
                                    @else
                                        <img src="{{ asset('img/icon.png') }}" alt="ユーザーアイコン">
                                    @endif
                                </div>
                                <span class="message__user-name">{{ $message->sender->name }}</span>
                            </div>
                            <div class="message__content">
                                @if($message->image_path)
                                    <div class="message__image">
                                        @if(file_exists(public_path('storage/' . $message->image_path)))
                                            <img src="{{ asset('storage/' . $message->image_path) }}" alt="送信画像" onclick="openImageModal('{{ asset('storage/' . $message->image_path) }}')">
                                        @else
                                            <div class="image-error">
                                                <p>画像が見つかりません</p>
                                                <small>パス: {{ $message->image_path }}</small>
                                            </div>
                                        @endif
                                    </div>
                                @endif
                                @if($message->message)
                                    <p class="message__text">{{ $message->message }}</p>
                                @endif
                                <span class="message__time">{{ $message->created_at->format('Y/m/d H:i') }}</span>
                                @if($message->is_edited)
                                    <span class="message__edited">(編集済み)</span>
                                @endif
                            </div>
                            @if($message->sender_id === $user->id && $message->created_at->diffInMinutes(now()) <= 15)
                                <div class="message__actions">
                                    @if($message->message)
                                        <button class="btn-edit" onclick="editMessage({{ $message->id }}, '{{ addslashes($message->message) }}')">編集</button>
                                    @endif
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
                @if($isCompleted)
                    <!-- 取引完了後のメッセージ -->
                    <div class="transaction-completed-message">
                        <p>取引が完了しました。</p>
                        @if($canRate && !$hasRated)
                            <p>今回の取引相手はどうでしたか？</p>
                            <div class="rating-stars">
                                <span class="star" data-rating="1">★</span>
                                <span class="star" data-rating="2">★</span>
                                <span class="star" data-rating="3">★</span>
                                <span class="star" data-rating="4">★</span>
                                <span class="star" data-rating="5">★</span>
                            </div>
                            <button type="button" class="btn-rating" onclick="openRatingModal()">送信する</button>
                        @elseif($hasRated)
                            <p>評価済みです</p>
                        @endif
                    </div>
                @else
                    <!-- 通常のメッセージ入力フォーム -->
                    <form action="{{ route('chat.message.send', $item->id) }}" method="POST" class="message-form" enctype="multipart/form-data">
                        @csrf
                        <div class="input-container">
                            <textarea name="message" placeholder="メッセージを入力してください" class="message-input"></textarea>
                            <div class="image-upload-container">
                                <input type="file" name="image" id="image-input" accept="image/*" style="display: none;" onchange="previewImage(event)">
                                <button type="button" class="btn-image" onclick="document.getElementById('image-input').click()">
                                    画像を追加
                                </button>
                                <div id="image-preview" style="display: none;">
                                    <img id="preview-img" src="" alt="プレビュー">
                                    <button type="button" class="btn-remove-image" onclick="removeImage()">×</button>
                                </div>
                            </div>
                            <button type="submit" class="btn-send">
                                <img src="{{ asset('img/send.jpg') }}" alt="送信" class="send-icon">
                            </button>
                        </div>
                    </form>
                @endif
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
            <p>今回の取引相手はどうでしたか？</p>
            <form id="ratingForm">
                @csrf
                <input type="hidden" name="item_id" value="{{ $item->id }}">
                @php
                    $partnerId = null;
                    if ($soldItem && $soldItem->user_id === $user->id) {
                        // 購入者の場合、出品者を評価対象とする
                        $partnerId = $item->user_id;
                    } elseif ($item->user_id === $user->id) {
                        // 出品者の場合、購入者を評価対象とする
                        $partnerId = $soldItem->user_id;
                    }
                @endphp
                <input type="hidden" name="rated_user_id" value="{{ $partnerId }}">

                <div class="rating-input">
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
                <div class="modal-actions">
                    <button type="button" class="btn-cancel" onclick="closeRatingModal()">キャンセル</button>
                    <button type="submit" class="btn-submit">送信する</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- 取引完了モーダル -->
<div id="completeModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>取引完了</h3>
            <span class="close" onclick="closeCompleteModal()">&times;</span>
        </div>
        <div class="modal-body">
            <p>取引を完了しますか？</p>
            <p>取引完了後は、取引相手を評価することができます。</p>
            <div class="modal-actions">
                <button type="button" class="btn-cancel" onclick="closeCompleteModal()">キャンセル</button>
                <button type="button" class="btn-submit" onclick="confirmCompleteTransaction()">取引を完了する</button>
            </div>
        </div>
    </div>
</div>

<!-- 画像モーダル -->
<div id="imageModal" class="modal">
    <div class="modal-content image-modal-content">
        <div class="modal-header">
            <span class="close" onclick="closeImageModal()">&times;</span>
        </div>
        <div class="modal-body">
            <img id="modal-image" src="" alt="画像">
        </div>
    </div>
</div>

<script>
// 取引完了モーダル
function openCompleteModal() {
    document.getElementById('completeModal').style.display = 'block';
}

function closeCompleteModal() {
    document.getElementById('completeModal').style.display = 'none';
}

// 取引完了確認
function confirmCompleteTransaction() {
    closeCompleteModal();
    completeTransaction();
}

// 取引完了処理
function completeTransaction() {
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
            // 取引完了後、すぐに評価モーダルを表示
            setTimeout(() => {
                openRatingModal();
            }, 500);
        } else {
            alert(data.error || '取引完了に失敗しました');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('取引完了に失敗しました');
    });
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
    const imageModal = document.getElementById('imageModal');

    if (event.target == ratingModal) {
        ratingModal.style.display = 'none';
    }

    if (event.target == imageModal) {
        imageModal.style.display = 'none';
    }
}

// 星評価の処理（取引完了メッセージ内の星）
document.querySelectorAll('.rating-stars .star').forEach((star, index) => {
    star.addEventListener('click', function() {
        const rating = this.getAttribute('data-rating');

        // 全ての星をリセット
        document.querySelectorAll('.rating-stars .star').forEach(s => {
            s.classList.remove('selected');
        });

        // クリックした星まで選択状態にする
        for (let i = 0; i < rating; i++) {
            document.querySelectorAll('.rating-stars .star')[i].classList.add('selected');
        }

        // モーダル内の対応するラジオボタンを選択
        document.getElementById(`star${rating}`).checked = true;
    });
});

// モーダル内の星評価の処理
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

        // 取引完了メッセージ内の星も同期
        document.querySelectorAll('.rating-stars .star').forEach(s => {
            s.classList.remove('selected');
        });
        for (let i = 0; i < rating; i++) {
            document.querySelectorAll('.rating-stars .star')[i].classList.add('selected');
        }
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
            // 評価完了後に商品一覧画面にリダイレクト
            window.location.href = '/';
        } else {
            alert(data.error || '評価の送信に失敗しました');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('評価の送信に失敗しました');
    });
});

// ページ読み込み時の処理
document.addEventListener('DOMContentLoaded', function() {
    // URLパラメータをチェック
    const urlParams = new URLSearchParams(window.location.search);
    const showRating = urlParams.get('show_rating');

    // 購入者で取引完了済みで未評価の場合、評価モーダルを自動表示
    @if($isBuyer && $isCompleted && !$hasRated)
        if (showRating === 'true') {
            setTimeout(() => {
                openRatingModal();
            }, 500);
        }
    @endif
});

function editMessage(messageId, currentMessage) {
    const newMessage = prompt('メッセージを編集してください:', currentMessage);
    if (newMessage !== null && newMessage.trim() !== '') {
        fetch(`/messages/${messageId}`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({
                message: newMessage
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert(data.error || 'メッセージの編集に失敗しました');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('メッセージの編集に失敗しました');
        });
    }
}

// 画像プレビュー
function previewImage(event) {
    const file = event.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const preview = document.getElementById('image-preview');
            const previewImg = document.getElementById('preview-img');
            previewImg.src = e.target.result;
            preview.style.display = 'block';
        };
        reader.readAsDataURL(file);
    }
}

// 画像削除
function removeImage() {
    const imageInput = document.getElementById('image-input');
    const preview = document.getElementById('image-preview');
    imageInput.value = '';
    preview.style.display = 'none';
}

// 画像モーダル
function openImageModal(imageSrc) {
    const modal = document.getElementById('imageModal');
    const modalImage = document.getElementById('modal-image');
    modalImage.src = imageSrc;
    modal.style.display = 'block';
}

function closeImageModal() {
    document.getElementById('imageModal').style.display = 'none';
}
</script>

@endsection
