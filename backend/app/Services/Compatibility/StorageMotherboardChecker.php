<?php

namespace App\Services\Compatibility;

use Illuminate\Support\Collection;

class StorageMotherboardChecker implements CompatibilityChecker
{
    public function check(Collection $itemsBySlot): ?bool
    {
        $storageItems = $itemsBySlot->get('storage');
        $motherboard = $itemsBySlot->get('motherboard')?->first()?->product;

        if (! $storageItems || $storageItems->isEmpty() || ! $motherboard) {
            return null;
        }

        // A motherboard supports multiple interfaces, stored as a comma-separated
        // list in the same wide `storage_interface` column (e.g. "SATA,NVMe,M.2").
        $moboInterfaces = $motherboard->specification?->storage_interface;

        if (! $moboInterfaces) {
            return null;
        }

        $supported = array_map(fn ($i) => strtoupper(trim($i)), explode(',', $moboInterfaces));
        $checked = false;

        foreach ($storageItems as $storageItem) {
            $interface = $storageItem->product->specification?->storage_interface;
            if (! $interface) {
                continue;
            }
            $checked = true;
            if (! in_array(strtoupper(trim($interface)), $supported, true)) {
                return false;
            }
        }

        return $checked ? true : null;
    }
}
