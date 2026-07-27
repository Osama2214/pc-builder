<?php

namespace App\Http\Requests\Product;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category_id' => ['sometimes', 'required', 'integer', 'exists:categories,id'],
            'brand_id' => ['sometimes', 'required', 'integer', 'exists:brands,id'],
            'sku' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('products', 'sku')->ignore($this->route('product'))],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            // Not nullable: the slug column has no DB default, so an explicit null
            // here would otherwise pass validation and crash as a raw 500 on save.
            'slug' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('products', 'slug')->ignore($this->route('product'))],
            'description' => ['sometimes', 'nullable', 'string'],
            'price' => ['sometimes', 'required', 'numeric', 'min:0'],
            'sale_price' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'discount' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'stock' => ['sometimes', 'required', 'integer', 'min:0'],
            'thumbnail' => ['sometimes', 'nullable', 'string', 'max:255'],
            'weight' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'warranty_months' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'required', 'boolean'],

            'specifications' => ['sometimes', 'array'],
            'specifications.socket' => ['nullable', 'string', 'max:255'],
            'specifications.chipset' => ['nullable', 'string', 'max:255'],
            'specifications.form_factor' => ['nullable', 'string', 'max:255'],
            'specifications.cores' => ['nullable', 'integer', 'min:0'],
            'specifications.threads' => ['nullable', 'integer', 'min:0'],
            'specifications.clock_speed' => ['nullable', 'string', 'max:255'],
            'specifications.cpu_generation' => ['nullable', 'string', 'max:255'],
            'specifications.pcie_version' => ['nullable', 'string', 'max:255'],
            'specifications.pcie_slots' => ['nullable', 'integer', 'min:0'],
            'specifications.ram_type' => ['nullable', 'string', 'max:255'],
            'specifications.memory_type' => ['nullable', 'string', 'max:255'],
            'specifications.memory_size' => ['nullable', 'string', 'max:255'],
            'specifications.max_memory' => ['nullable', 'string', 'max:255'],
            'specifications.memory_slots' => ['nullable', 'integer', 'min:0'],
            'specifications.capacity' => ['nullable', 'string', 'max:255'],
            'specifications.power_draw' => ['nullable', 'integer', 'min:0'],
            'specifications.wattage' => ['nullable', 'integer', 'min:0'],
            'specifications.efficiency_rating' => ['nullable', 'string', 'max:255'],
            'specifications.max_gpu_length' => ['nullable', 'integer', 'min:0'],
            'specifications.boost_clock' => ['nullable', 'string', 'max:255'],
            'specifications.architecture' => ['nullable', 'string', 'max:255'],
            'specifications.integrated_graphics' => ['nullable', 'string', 'max:255'],
            'specifications.cache_size' => ['nullable', 'string', 'max:255'],
            'specifications.m2_slots' => ['nullable', 'integer', 'min:0'],
            'specifications.sata_ports' => ['nullable', 'integer', 'min:0'],
            'specifications.wifi' => ['nullable', 'string', 'max:255'],
            'specifications.length_mm' => ['nullable', 'integer', 'min:0'],
            'specifications.video_ports' => ['nullable', 'string', 'max:255'],
            'specifications.ram_speed' => ['nullable', 'string', 'max:255'],
            'specifications.cas_latency' => ['nullable', 'string', 'max:255'],
            'specifications.kit_config' => ['nullable', 'string', 'max:255'],
            'specifications.storage_interface' => ['nullable', 'string', 'max:255'],
            'specifications.storage_type' => ['nullable', 'string', 'max:255'],
            'specifications.read_speed' => ['nullable', 'integer', 'min:0'],
            'specifications.write_speed' => ['nullable', 'integer', 'min:0'],
            'specifications.modular_type' => ['nullable', 'string', 'max:255'],
            'specifications.l1_cache' => ['nullable', 'string', 'max:255'],
            'specifications.l2_cache' => ['nullable', 'string', 'max:255'],
            'specifications.l3_cache' => ['nullable', 'string', 'max:255'],
            'specifications.custom_specifications' => ['nullable', 'array'],
            'specifications.custom_specifications.*.key' => ['required', 'string', 'max:100'],
            'specifications.custom_specifications.*.value' => ['nullable', 'string', 'max:500'],
            'specifications.cooler_type' => ['nullable', 'string', 'max:255'],
            'specifications.fan_size' => ['nullable', 'string', 'max:255'],
            'specifications.max_tdp' => ['nullable', 'integer', 'min:0'],
            'specifications.screen_size' => ['nullable', 'string', 'max:255'],
            'specifications.resolution' => ['nullable', 'string', 'max:255'],
            'specifications.refresh_rate' => ['nullable', 'string', 'max:255'],
            'specifications.panel_type' => ['nullable', 'string', 'max:255'],
            'specifications.response_time' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * sale_price must be less than price, but on a partial update "price" may not
     * be part of this request — fall back to the product's current DB price so the
     * comparison is still correct (a plain 'lt:price' rule would skip validation
     * whenever price isn't resent).
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $salePrice = $this->input('sale_price');

            if ($salePrice === null) {
                return;
            }

            $product = $this->route('product');
            $effectivePrice = $this->input('price', $product?->price);

            if ($effectivePrice !== null && (float) $salePrice >= (float) $effectivePrice) {
                $validator->errors()->add('sale_price', 'The sale price must be less than the price.');
            }
        });
    }
}
