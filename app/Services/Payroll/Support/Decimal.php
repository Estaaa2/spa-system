<?php

declare(strict_types=1);

namespace App\Services\Payroll\Support;

use InvalidArgumentException;

/**
 * bcmath helpers for the earnings engine. Same conventions as StatutoryCalculator:
 * decimal strings in, decimal strings out, intermediate scale 6, half-up
 * (away from zero) rounding. Floats are never accepted.
 */
final class Decimal
{
    public const SCALE = 6;

    /** Normalize a DB value (decimal-cast string, int, or numeric string) to a scale-6 string. */
    public static function of(string|int|null $value, string $name = 'value'): string
    {
        $value = $value === null ? '0' : (string) $value;

        if (preg_match('/^-?\d+(\.\d+)?$/', $value) !== 1) {
            throw new InvalidArgumentException("{$name} must be a plain decimal string, got [{$value}].");
        }

        return bcadd($value, '0', self::SCALE);
    }

    /**
     * For values read from the DB. Uncast decimal columns can arrive as float
     * (PDO SQLite on PHP 8.1+); those are formatted once at 6 dp, never used in math.
     */
    public static function fromDb(mixed $value, string $name = 'value'): string
    {
        if (is_float($value)) {
            $value = sprintf('%.6F', $value);
        }

        return self::of($value === null ? null : (is_int($value) ? $value : (string) $value), $name);
    }

    public static function add(string $a, string $b): string
    {
        return bcadd($a, $b, self::SCALE);
    }

    public static function sub(string $a, string $b): string
    {
        return bcsub($a, $b, self::SCALE);
    }

    public static function mul(string $a, string $b): string
    {
        return bcmul($a, $b, self::SCALE);
    }

    public static function div(string $a, string $b): string
    {
        if (bccomp($b, '0', self::SCALE) === 0) {
            throw new InvalidArgumentException('Division by zero.');
        }

        return bcdiv($a, $b, self::SCALE);
    }

    /** Half-up (away from zero) to $places decimals. */
    public static function round(string $value, int $places): string
    {
        $half = '0.'.str_repeat('0', $places).'5';
        $half = str_starts_with($value, '-') ? '-'.$half : $half;

        return bcadd($value, $half, $places);
    }

    public static function round2(string $value): string
    {
        return self::round($value, 2);
    }

    public static function round4(string $value): string
    {
        return self::round($value, 4);
    }

    public static function cmp(string $a, string $b): int
    {
        return bccomp($a, $b, self::SCALE);
    }

    public static function isPositive(string $a): bool
    {
        return self::cmp($a, '0') > 0;
    }

    public static function min(string $a, string $b): string
    {
        return self::cmp($a, $b) <= 0 ? $a : $b;
    }

    public static function max(string $a, string $b): string
    {
        return self::cmp($a, $b) >= 0 ? $a : $b;
    }

    public static function neg(string $a): string
    {
        return bcsub('0', $a, self::SCALE);
    }

    /** Peso display for notes, e.g. '1,500.00'. */
    public static function peso(string $a): string
    {
        $r = self::round2($a);
        $neg = str_starts_with($r, '-');
        [$int, $frac] = explode('.', ltrim($r, '-'));

        return ($neg ? '-' : '').strrev(implode(',', str_split(strrev($int), 3))).'.'.$frac;
    }
}
