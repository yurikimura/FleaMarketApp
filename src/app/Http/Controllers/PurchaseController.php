<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Http\Requests\AddressRequest;
use App\Models\Item;
use App\Models\User;
use App\Models\SoldItem;
use App\Models\Profile;


class PurchaseController extends Controller
{
    public function index($item_id, Request $request){
        $item = Item::find($item_id);
        $user = User::find(Auth::id());

        // ユーザーの住所情報をチェック
        if (!$user->profile || !$user->profile->postcode || !$user->profile->address) {
            return redirect('/mypage/profile')->with('message', '商品を購入するには住所情報の登録が必要です。プロフィール設定を完了してください。');
        }

        // $data = $request->session()->put('item_id',$item_id);
        return view('purchase',compact('item','user'));
    }

    public function purchase($item_id){

        $item = Item::find($item_id);
        if ($item->user_id !== Auth::id()){
            SoldItem::create([
                'user_id' => Auth::id(),
                'item_id' => $item_id
            ]);

            // 購入後はチャット画面にリダイレクト
            return redirect()->route('chat.show', $item_id)->with('success', '商品を購入しました。出品者とのやり取りを開始できます。');
        }
        return redirect('/');
    }

    public function address($item_id, Request $request){
        $user = User::find(Auth::id());
        return view('address', compact('user','item_id'));
    }

    public function updateAddress(AddressRequest $request){

        $user = User::find(Auth::id());
        Profile::where('user_id', $user->id)->update([
            'postcode' => $request->postcode,
            'address' => $request->address,
            'building' => $request->building
        ]);

        // $item_id = $request->session()->get('item_id');
        return redirect()->route('purchase.index', ['item_id' => $request->item_id]);
    }
}
