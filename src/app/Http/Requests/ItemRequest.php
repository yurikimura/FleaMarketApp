<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Symfony\Contracts\Service\Attribute\Required;

class ItemRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'name' => 'required',
            'price' => ['required', 'integer'],
            'description' => 'required',
            'img_url' => 'required',
            'categories' => 'required',
            'condition_id' => 'required'
        ];
    }

    public function messages()
    {
        return [
            'name.required' => '商品名を記入してください',
            'price.required' => '価格を記入してください',
            'price.integer' => '価格は数値で入力してください',
            'description.required' => '説明文を記入してください',
            'img_url.required' => '画像を選んでください',
            'categories.required' => 'カテゴリーを選んでください',
            'condition_id.required' => '状態を選んでください',
        ];
    }
}
