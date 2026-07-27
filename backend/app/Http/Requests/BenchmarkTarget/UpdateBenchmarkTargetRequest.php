<?php

namespace App\Http\Requests\BenchmarkTarget;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBenchmarkTargetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'type' => ['sometimes', 'required', 'string', Rule::in(['game', 'software'])],
            'image' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
