<?php

namespace App\Services;

use App\Models\Build;
use App\Services\Compatibility\CompatibilityChecker;
use App\Services\Compatibility\CoolerCpuChecker;
use App\Services\Compatibility\CpuMotherboardChecker;
use App\Services\Compatibility\GpuCaseChecker;
use App\Services\Compatibility\GpuPsuChecker;
use App\Services\Compatibility\RamMotherboardChecker;
use App\Services\Compatibility\StorageMotherboardChecker;

class CompatibilityService
{
    /** @var CompatibilityChecker[] */
    private array $checkers;

    public function __construct()
    {
        $this->checkers = [
            new CpuMotherboardChecker(),
            new RamMotherboardChecker(),
            new CoolerCpuChecker(),
            new GpuPsuChecker(),
            new GpuCaseChecker(),
            new StorageMotherboardChecker(),
        ];
    }

    /**
     * Business rule 7: a missing/uncheckable pair is skipped, never treated as an
     * error. "incompatible" wins if any checker actually ran and failed.
     * "incomplete" means nothing could be checked at all yet (too few components).
     * Otherwise "compatible".
     */
    public function check(Build $build): string
    {
        $build->loadMissing('items.product.specification');

        $itemsBySlot = $build->items->groupBy('slot');

        $ranAny = false;

        foreach ($this->checkers as $checker) {
            $result = $checker->check($itemsBySlot);

            if ($result === false) {
                return 'incompatible';
            }

            if ($result === true) {
                $ranAny = true;
            }
        }

        return $ranAny ? 'compatible' : 'incomplete';
    }
}
