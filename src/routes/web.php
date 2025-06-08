<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ItemController;
use App\Http\Controllers\LikeController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\AuthController;

use App\Http\Middleware\SoldItemMiddleware;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

// 認証関連のルート
Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
Route::post('/login', [AuthController::class, 'login']);
Route::get('/register', [AuthController::class, 'showRegisterForm'])->name('register');
Route::post('/register', [AuthController::class, 'register']);
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// 公開ルート
Route::get('/',[ItemController::class, 'index']);
Route::get('/item/{item}',[ItemController::class, 'detail'])->name('item.detail');
Route::get('/search', [ItemController::class, 'search'])->name('item.search');

// 認証が必要なルート
Route::middleware('auth')->group(function () {
    Route::get('/sell',[ItemController::class, 'sellView']);
    Route::post('/sell',[ItemController::class, 'sellCreate']);
    Route::post('/item/like/{item_id}',[LikeController::class, 'create']);
    Route::post('/item/unlike/{item_id}',[LikeController::class, 'destroy']);
    Route::post('/item/comment/{item_id}',[CommentController::class, 'create']);
    Route::get('/purchase/{item_id}',[PurchaseController::class, 'index'])->middleware('purchase')->name('purchase.index');
    Route::post('/purchase/{item_id}',[PurchaseController::class, 'purchase'])->middleware('purchase');
    Route::get('/purchase/address/{item_id}',[PurchaseController::class, 'address']);
    Route::post('/purchase/address/{item_id}',[PurchaseController::class, 'updateAddress']);
    Route::get('/mypage', [UserController::class, 'mypage']);
    Route::get('/mypage/profile', [UserController::class, 'profile']);
    Route::post('/mypage/profile', [UserController::class, 'updateProfile']);
    Route::get('/chat/{item_id}', [UserController::class, 'chat'])->name('chat.show');
    Route::post('/chat/{item_id}', [UserController::class, 'sendMessage'])->name('chat.send');
    Route::post('/chat/{item_id}/message', [UserController::class, 'sendMessage'])->name('chat.message.send');
    Route::put('/messages/{message}', [UserController::class, 'updateMessage'])->name('message.update');
    Route::delete('/messages/{message}', [UserController::class, 'deleteMessage'])->name('message.delete');
    Route::get('/mypage/{user_id}/chat/{item_id}', [UserController::class, 'chat'])->name('chat');
    Route::post('/mypage/{user_id}/chat/{item_id}/send', [UserController::class, 'sendMessage'])->name('chat.send');
    Route::post('/transaction/{item_id}/complete', [UserController::class, 'completeTransaction'])->name('transaction.complete');
    Route::post('/rating/store', [UserController::class, 'storeRating'])->name('rating.store');
});
