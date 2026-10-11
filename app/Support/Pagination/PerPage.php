<?php

namespace App\Support\Pagination;

final class PerPage
{
    public const DEFAULT = 15;
    public const MAX     = 100;

    /**
     * Coerce a raw per_page value into a sane integer.
     *
     * Missing, non-numeric, zero, and negative values fall back to the
     * default. Values above the max are clamped to the max.
     */
    public static function resolve(
        mixed $value,
        int $default = self::DEFAULT,
        int $max = self::MAX,
    ): int {
        // FILTER_VALIDATE_INT returns false for 'abc', '', null, and '2.5',
        // but returns the int for '2' and 2.
        $validated = filter_var($value, FILTER_VALIDATE_INT);

        if ($validated === false || $validated < 1) {
            return $default;
        }

        return min($validated, $max);
    }
}