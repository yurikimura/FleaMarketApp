<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MessageRequest extends FormRequest
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
        // メッセージ編集の場合は、メッセージが必須
        if ($this->isMethod('PUT') || $this->isMethod('PATCH')) {
            return [
                'message' => 'required|string|max:400',
            ];
        }

        // メッセージ送信の場合
        return [
            'message' => 'required|string|max:400',
            'image' => 'nullable|image|mimes:png,jpeg|max:2048',
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array
     */
    public function messages()
    {
        return [
            'message.required' => '本文を入力してください',
            'message.max' => '本文は400文字以内で入力してください',
            'image.image' => '画像ファイルをアップロードしてください',
            'image.mimes' => '「.png」または「.jpeg」形式でアップロードしてください',
            'image.max' => '画像サイズは2MB以下にしてください',
        ];
    }

    /**
     * Get the custom attributes for validator errors.
     *
     * @return array
     */
    public function attributes()
    {
        return [
            'message' => 'メッセージ',
            'image' => '画像',
        ];
    }
}
