<?php

namespace App\Http\Requests\Build;

use App\Services\BuildService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AddBuildItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'slot' => ['required', 'string', Rule::in(BuildService::ALL_SLOTS)],
            'quantity' => ['sometimes', 'integer', 'min:1'],
        ];
    }
}
