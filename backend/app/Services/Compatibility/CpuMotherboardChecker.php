<?php

namespace App\Services\Compatibility;

use Illuminate\Support\Collection;

class CpuMotherboardChecker implements CompatibilityChecker
{
    private string $reason = '';

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

        $compatible = strcasecmp($cpuSocket, $moboSocket) === 0;

        if (! $compatible) {
            $this->reason = "The CPU socket ({$cpuSocket}) doesn't match the motherboard's socket ({$moboSocket}).";
        }

        return $compatible;
    }

    public function reason(): string
    {
        return $this->reason;
    }
}
