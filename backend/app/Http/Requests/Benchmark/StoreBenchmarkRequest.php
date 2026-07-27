<?php

namespace App\Http\Requests\Benchmark;

use App\Models\BenchmarkTarget;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreBenchmarkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'benchmark_target_id' => ['required', 'integer', 'exists:benchmark_targets,id'],
            'resolution' => ['nullable', 'string', 'max:255'],
            'quality' => ['nullable', 'string', 'max:255'],
            'fps' => ['nullable', 'integer', 'min:0'],
            'score' => ['nullable', 'integer', 'min:0'],
            'unit' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Business rule 10: fps is required for "game" targets, score for "software"
     * targets — at least the field matching the target's type must be present.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $target = BenchmarkTarget::find($this->input('benchmark_target_id'));

            if (! $target) {
                return;
            }

            if ($target->type === 'game' && ! $this->filled('fps')) {
                $validator->errors()->add('fps', 'fps is required for game benchmark targets.');
            } elseif ($target->type === 'software' && ! $this->filled('score')) {
                $validator->errors()->add('score', 'score is required for software benchmark targets.');
            }
        });
    }
}
