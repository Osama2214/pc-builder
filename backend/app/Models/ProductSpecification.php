<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductSpecification extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'socket',
        'chipset',
        'form_factor',
        'cores',
        'threads',
        'clock_speed',
        'cpu_generation',
        'pcie_version',
        'pcie_slots',
        'ram_type',
        'memory_type',
        'memory_size',
        'max_memory',
        'memory_slots',
        'capacity',
        'power_draw',
        'wattage',
        'efficiency_rating',
        'max_gpu_length',
        'boost_clock',
        'architecture',
        'integrated_graphics',
        'cache_size',
        'm2_slots',
        'sata_ports',
        'wifi',
        'length_mm',
        'video_ports',
        'ram_speed',
        'cas_latency',
        'kit_config',
        'storage_interface',
        'storage_type',
        'read_speed',
        'write_speed',
        'modular_type',
        'l1_cache',
        'l2_cache',
        'l3_cache',
        'custom_specifications',
        'cooler_type',
        'fan_size',
        'max_tdp',
        'screen_size',
        'resolution',
        'refresh_rate',
        'panel_type',
        'response_time',
    ];

    protected $casts = [
        'custom_specifications' => 'array',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
