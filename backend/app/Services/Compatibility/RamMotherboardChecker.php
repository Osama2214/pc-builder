<?php

namespace App\Services\Compatibility;

use Illuminate\Support\Collection;

class RamMotherboardChecker implements CompatibilityChecker
{
    private string $reason = '';

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
                    $this->reason = "RAM type ({$ramType}) isn't supported by the motherboard, which needs {$moboRamType}.";

                    return false;
                }
            }
        }

        if ($moboSlots) {
            $checked = true;
            $ramCount = $ramItems->sum('quantity');
            if ($ramCount > $moboSlots) {
                $this->reason = "You've selected {$ramCount} RAM stick(s), but the motherboard only has {$moboSlots} slot(s).";

                return false;
            }
        }

        return $checked ? true : null;
    }

    public function reason(): string
    {
        return $this->reason;
    }
}
