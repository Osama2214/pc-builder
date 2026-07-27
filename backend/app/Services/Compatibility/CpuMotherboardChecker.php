<?php

namespace App\Services\Compatibility;

use Illuminate\Support\Collection;

class CpuMotherboardChecker implements CompatibilityChecker
{
    public function check(Collection $itemsBySlot): ?bool
    {
        $cpu = $itemsBySlot->get('cpu')?->first()?->product;
        $motherboard = $itemsBySlot->get('motherboard')?->first()?->product;

        if (! $cpu || ! $motherboard) {
            return null;
        }

        $cpuSocket = $cpu->specification?->socket;
        $moboSocket = $motherboard->specification?->socket;

        if (! $cpuSocket || ! $moboSocket) {
            return null;
        }

        return strcasecmp($cpuSocket, $moboSocket) === 0;
    }
}
