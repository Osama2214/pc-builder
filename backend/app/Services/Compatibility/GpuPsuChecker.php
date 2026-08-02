<?php

namespace App\Services\Compatibility;

use Illuminate\Support\Collection;

class GpuPsuChecker implements CompatibilityChecker
{
    // Recommend 20% headroom over raw component draw, matching typical PC-builder guidance.
    private const HEADROOM_MULTIPLIER = 1.2;

    private string $reason = '';

    public function check(Collection $itemsBySlot): ?bool
    {
        $psu = $itemsBySlot->get('psu')?->first()?->product;

        if (! $psu) {
            return null;
        }

        $psuWattage = $psu->specification?->wattage;

        if (! $psuWattage) {
            return null;
        }

        $totalDraw = 0;
        $hasDrawData = false;

        foreach (['cpu', 'gpu'] as $slot) {
            $draw = $itemsBySlot->get($slot)?->first()?->product?->specification?->power_draw;

            if ($draw) {
                $totalDraw += $draw;
                $hasDrawData = true;
            }
        }

        if (! $hasDrawData) {
            return null;
        }

        $required = $totalDraw * self::HEADROOM_MULTIPLIER;
        $compatible = $psuWattage >= $required;

        if (! $compatible) {
            $this->reason = "The PSU ({$psuWattage}W) may not be enough — the CPU/GPU need about ".ceil($required)."W with recommended headroom.";
        }

        return $compatible;
    }

    public function reason(): string
    {
        return $this->reason;
    }
}
