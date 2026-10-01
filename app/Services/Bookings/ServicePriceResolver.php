<?php

declare(strict_types=1);

namespace App\Services\Bookings;

use App\Models\Package;
use App\Models\Treatment;

final class ServicePriceResolver
{
    public const TREATMENT = 'treatment';
    public const PACKAGE = 'package';

    /** @return array{type: string, id: int}|null */
    public function parse(?string $code): ?array
    {
        if ($code === null) {
            return null;
        }
        if (str_starts_with($code, 'treatment_')) {
            return ['type' => self::TREATMENT, 'id' => (int) str_replace('treatment_', '', $code)];
        }
        if (str_starts_with($code, 'package_')) {
            return ['type' => self::PACKAGE, 'id' => (int) str_replace('package_', '', $code)];
        }

        return null;
    }

    /** List price as a 2-dp decimal string; '0.00' when the code or record is unknown. */
    public function listPrice(?string $code): string
    {
        $target = $this->parse($code);
        if ($target === null) {
            return '0.00';
        }

        $model = $target['type'] === self::TREATMENT
            ? Treatment::withoutGlobalScopes()->find($target['id'])
            : Package::withoutGlobalScopes()->find($target['id']);

        return self::normalize($model?->price);
    }

    /** DB decimals arrive as strings; tolerate int/float from uncast columns without float math. */
    public static function normalize(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '0.00';
        }
        if (is_float($value)) {
            $value = sprintf('%.2f', $value);
        }
        $value = (string) $value;

        return preg_match('/^-?\d+(\.\d+)?$/', $value) === 1 ? bcadd($value, '0', 2) : '0.00';
    }
}
