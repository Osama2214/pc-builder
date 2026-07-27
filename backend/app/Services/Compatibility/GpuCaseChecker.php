<?php

namespace App\Services\Compatibility;

use Illuminate\Support\Collection;

class GpuCaseChecker implements CompatibilityChecker
{
    public function check(Collection $itemsBySlot): ?bool
    {
        $gpu = $itemsBySlot->get('gpu')?->first()?->product;
        $case = $itemsBySlot->get('case')?->first()?->product;

        if (! $gpu || ! $case) {
            return null;
        }

        $gpuLength = $gpu->specification?->length_mm;
        $maxGpuLength = $case->specification?->max_gpu_length;

        if (! $gpuLength || ! $maxGpuLength) {
            return null;
        }

        return $gpuLength <= $maxGpuLength;
    }
}
