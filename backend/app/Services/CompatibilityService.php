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
        return $this->evaluate($build)['status'];
    }

    /**
     * Human-readable explanations for every failing checker (not just the first) —
     * empty unless check() would return "incompatible".
     *
     * @return string[]
     */
    public function reasons(Build $build): array
    {
        return $this->evaluate($build)['reasons'];
    }

    /**
     * @return array{status: string, reasons: string[]}
     */
    private function evaluate(Build $build): array
    {
        $build->loadMissing('items.product.specification');

        $itemsBySlot = $build->items->groupBy('slot');

        $ranAny = false;
        $reasons = [];

        foreach ($this->checkers as $checker) {
            $result = $checker->check($itemsBySlot);

            if ($result === false) {
                $reasons[] = $checker->reason();
            } elseif ($result === true) {
                $ranAny = true;
            }
        }

        $status = $reasons !== [] ? 'incompatible' : ($ranAny ? 'compatible' : 'incomplete');

        return ['status' => $status, 'reasons' => $reasons];
    }
}
