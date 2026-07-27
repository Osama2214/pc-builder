<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductSpecificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'socket' => $this->socket,
            'chipset' => $this->chipset,
            'form_factor' => $this->form_factor,
            'cores' => $this->cores,
            'threads' => $this->threads,
            'clock_speed' => $this->clock_speed,
            'cpu_generation' => $this->cpu_generation,
            'pcie_version' => $this->pcie_version,
            'pcie_slots' => $this->pcie_slots,
            'ram_type' => $this->ram_type,
            'memory_type' => $this->memory_type,
            'memory_size' => $this->memory_size,
            'max_memory' => $this->max_memory,
            'memory_slots' => $this->memory_slots,
            'capacity' => $this->capacity,
            'power_draw' => $this->power_draw,
            'wattage' => $this->wattage,
            'efficiency_rating' => $this->efficiency_rating,
            'max_gpu_length' => $this->max_gpu_length,
            'boost_clock' => $this->boost_clock,
            'architecture' => $this->architecture,
            'integrated_graphics' => $this->integrated_graphics,
            'cache_size' => $this->cache_size,
            'm2_slots' => $this->m2_slots,
            'sata_ports' => $this->sata_ports,
            'wifi' => $this->wifi,
            'length_mm' => $this->length_mm,
            'video_ports' => $this->video_ports,
            'ram_speed' => $this->ram_speed,
            'cas_latency' => $this->cas_latency,
            'kit_config' => $this->kit_config,
            'storage_interface' => $this->storage_interface,
            'storage_type' => $this->storage_type,
            'read_speed' => $this->read_speed,
            'write_speed' => $this->write_speed,
            'modular_type' => $this->modular_type,
            'l1_cache' => $this->l1_cache,
            'l2_cache' => $this->l2_cache,
            'l3_cache' => $this->l3_cache,
            'custom_specifications' => $this->custom_specifications ?? [],
            'cooler_type' => $this->cooler_type,
            'fan_size' => $this->fan_size,
            'max_tdp' => $this->max_tdp,
            'screen_size' => $this->screen_size,
            'resolution' => $this->resolution,
            'refresh_rate' => $this->refresh_rate,
            'panel_type' => $this->panel_type,
            'response_time' => $this->response_time,
        ];
    }
}
