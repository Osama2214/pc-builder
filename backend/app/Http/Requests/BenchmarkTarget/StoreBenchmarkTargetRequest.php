<?php

namespace App\Http\Requests\BenchmarkTarget;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBenchmarkTargetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', Rule::in(['game', 'software'])],
            'image' => ['nullable', 'string', 'max:255'],
        ];
    }
}
