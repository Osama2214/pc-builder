<?php

namespace App\Services\Compatibility;

// Spec values in the catalog aren't always entered as a single clean token —
// a motherboard's socket might be the full marketing string ("AMD Ryzen 9000 /
// 8000 / 7000 Series Desktop Processors (Socket AM5)") instead of just "AM5".
// An exact case-insensitive comparison misses these, so two values are treated
// as a match if either one, once stripped down to just letters and digits,
// contains the other as a substring.
class SpecMatcher
{
    public static function matches(?string $a, ?string $b): bool
    {
        $normalize = fn (string $value): string => strtoupper(preg_replace('/[^a-z0-9]/iu', '', $value) ?? '');

        $a = $a ? $normalize($a) : '';
        $b = $b ? $normalize($b) : '';

        if ($a === '' || $b === '') {
            return false;
        }

        return str_contains($a, $b) || str_contains($b, $a);
    }
}
