<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Reads table-filter query values safely (table filters workstream, Q1 A).
 * Unknown values fall back to "all", so a stale or edited URL never errors.
 */
final class TableFilters
{
    /** @param array<int|string, mixed> $allowed allowed values (keys of an options array are fine via array_keys) */
    public static function choice(Request $request, string $key, array $allowed): ?string
    {
        $value = $request->query($key);

        if (! is_string($value) && ! is_int($value)) {
            return null;
        }

        $value = (string) $value;

        return in_array($value, array_map('strval', $allowed), true) ? $value : null;
    }

    /** Trimmed search text, or null. */
    public static function search(Request $request, string $key = 'q'): ?string
    {
        $value = $request->query($key);
        if (! is_string($value)) {
            return null;
        }
        $value = trim(mb_substr($value, 0, 100));

        return $value === '' ? null : $value;
    }

    /** Case-insensitive "contains" across several strings. */
    public static function matches(?string $needle, ?string ...$haystacks): bool
    {
        if ($needle === null) {
            return true;
        }
        $needle = mb_strtolower($needle);
        foreach ($haystacks as $h) {
            if ($h !== null && str_contains(mb_strtolower($h), $needle)) {
                return true;
            }
        }

        return false;
    }

    /** @param array<string, mixed> $filters */
    public static function activeCount(array $filters): int
    {
        return count(array_filter($filters, fn ($v) => $v !== null && $v !== ''));
    }
}
