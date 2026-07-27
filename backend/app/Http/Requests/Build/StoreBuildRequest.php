<?php

namespace App\Http\Requests\Build;

use Illuminate\Foundation\Http\FormRequest;

class StoreBuildRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:255'],
        ];
    }
}
