<?php

namespace App\Services\Compatibility;

use Illuminate\Support\Collection;

class GpuCaseChecker implements CompatibilityChecker
{
    private string $reason = '';

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

        $compatible = $gpuLength <= $maxGpuLength;

        if (! $compatible) {
            $this->reason = "The GPU ({$gpuLength}mm) is too long to fit in the case (max {$maxGpuLength}mm).";
        }

        return $compatible;
    }

    public function reason(): string
    {
        return $this->reason;
    }
}
