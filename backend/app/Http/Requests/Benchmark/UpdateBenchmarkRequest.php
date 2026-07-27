<?php

namespace App\Http\Requests\Benchmark;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBenchmarkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'resolution' => ['sometimes', 'nullable', 'string', 'max:255'],
            'quality' => ['sometimes', 'nullable', 'string', 'max:255'],
            'fps' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'score' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'unit' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
