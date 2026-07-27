<?php

namespace App\Http\Requests\Product;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'brand_id' => ['required', 'integer', 'exists:brands,id'],
            'sku' => ['required', 'string', 'max:255', 'unique:products,sku'],
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:products,slug'],
            'description' => ['nullable', 'string'],
            'price' => ['required', 'numeric', 'min:0'],
            'sale_price' => ['nullable', 'numeric', 'min:0', 'lt:price'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'stock' => ['required', 'integer', 'min:0'],
            'thumbnail' => ['nullable', 'string', 'max:255'],
            'weight' => ['nullable', 'numeric', 'min:0'],
            'warranty_months' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['required', 'boolean'],

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
}
