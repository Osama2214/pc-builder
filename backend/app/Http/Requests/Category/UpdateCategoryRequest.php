<?php

namespace App\Http\Requests\Category;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            // Not nullable: the slug column has no DB default, so an explicit null
            // here would otherwise pass validation and crash as a raw 500 on save.
            'slug' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('categories', 'slug')->ignore($this->route('category'))],
            'icon' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
