<?php

namespace App\Http\Requests\Build;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBuildRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
