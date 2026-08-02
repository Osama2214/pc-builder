<?php

namespace App\Services\Compatibility;

use Illuminate\Support\Collection;

class CoolerCpuChecker implements CompatibilityChecker
{
    private string $reason = '';

    public function check(Collection $itemsBySlot): ?bool
    {
        $cooler = $itemsBySlot->get('cooler')?->first()?->product;
        $cpu = $itemsBySlot->get('cpu')?->first()?->product;

        if (! $cooler || ! $cpu) {
            return null;
        }

        $cpuSocket = $cpu->specification?->socket;
        // A cooler supports multiple sockets, stored as a comma-separated list
        // in the same wide `socket` column (e.g. "AM4,AM5,LGA1700").
        $coolerSockets = $cooler->specification?->socket;

        if (! $cpuSocket || ! $coolerSockets) {
            return null;
        }

        $supported = array_map(fn ($s) => strtoupper(trim($s)), explode(',', $coolerSockets));
        $compatible = in_array(strtoupper(trim($cpuSocket)), $supported, true);

        if (! $compatible) {
            $this->reason = "The cooler doesn't support the CPU's socket ({$cpuSocket}) — it supports: {$coolerSockets}.";
        }

        return $compatible;
    }

    public function reason(): string
    {
        return $this->reason;
    }
}
