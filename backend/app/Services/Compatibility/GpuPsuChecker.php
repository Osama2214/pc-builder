<?php

namespace App\Services\Compatibility;

use Illuminate\Support\Collection;

class GpuPsuChecker implements CompatibilityChecker
{
    // Recommend 20% headroom over raw component draw, matching typical PC-builder guidance.
    private const HEADROOM_MULTIPLIER = 1.2;

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

        return $psuWattage >= $totalDraw * self::HEADROOM_MULTIPLIER;
    }
}
