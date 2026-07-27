<?php

namespace App\Http\Requests\Build;

use Illuminate\Foundation\Http\FormRequest;

class ShareBuildRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'is_public' => ['required', 'boolean'],
        ];
    }
}
