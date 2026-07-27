<?php

namespace App\Http\Requests\Product;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'images' => ['required', 'array', 'min:1'],
            // 'image' alone can accept svg on some Laravel versions/configs — svg can carry
            // embedded <script> and is a stored-XSS vector if ever served/rendered raw, so
            // restrict explicitly to raster formats instead of relying on that default.
            'images.*' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'primary_index' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
