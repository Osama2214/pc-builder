<?php

namespace App\Services\Compatibility;

use Illuminate\Support\Collection;

interface CompatibilityChecker
{
    /**
     * @param  Collection<string, Collection<int, \App\Models\BuildItem>>  $itemsBySlot  build items grouped by slot, each with product.specification eager-loaded
     * @return bool|null true = compatible, false = incompatible, null = not enough data to check (missing slot or missing spec field) — never counts as an error
     */
    public function check(Collection $itemsBySlot): ?bool;
}
