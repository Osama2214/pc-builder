<?php

namespace App\Services\Compatibility;

use Illuminate\Support\Collection;

class RamMotherboardChecker implements CompatibilityChecker
{
    public function check(Collection $itemsBySlot): ?bool
    {
        $ramItems = $itemsBySlot->get('ram');
        $motherboard = $itemsBySlot->get('motherboard')?->first()?->product;

        if (! $ramItems || $ramItems->isEmpty() || ! $motherboard) {
            return null;
        }

        $moboRamType = $motherboard->specification?->ram_type;
        $moboSlots = $motherboard->specification?->memory_slots;

        if (! $moboRamType && ! $moboSlots) {
            return null;
        }

        $checked = false;

        if ($moboRamType) {
            foreach ($ramItems as $ramItem) {
                $ramType = $ramItem->product->specification?->ram_type;
                if (! $ramType) {
                    continue;
                }
                $checked = true;
                if (strcasecmp($ramType, $moboRamType) !== 0) {
                    return false;
                }
            }
        }

        if ($moboSlots) {
            $checked = true;
            if ($ramItems->sum('quantity') > $moboSlots) {
                return false;
            }
        }

        return $checked ? true : null;
    }
}
