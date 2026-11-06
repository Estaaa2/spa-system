<?php

namespace App\Support\Legal;

/**
 * The public contact details (config/levictas.php → operator, contact).
 * One source for the legal pages, the staff ID consent notice and, later,
 * the Contact page (landing footer Phase 2, Q2-A).
 */
class Contact
{
    public static function email(): string
    {
        return trim((string) config('levictas.contact.email'));
    }

    public static function address(): string
    {
        return trim((string) config('levictas.contact.address'));
    }

    public static function team(): string
    {
        return trim((string) config('levictas.operator.team'));
    }

    public static function school(): string
    {
        return trim((string) config('levictas.operator.school'));
    }

    /** True while a value is still an unfilled "[PLACEHOLDER]". */
    public static function isPlaceholder(string $value): bool
    {
        return $value === '' || (str_starts_with($value, '[') && str_ends_with($value, ']'));
    }

    /** Every contact/operator value that is still a placeholder (for tests and the report). */
    public static function placeholders(): array
    {
        return array_keys(array_filter([
            'levictas.operator.team'    => self::team(),
            'levictas.operator.school'  => self::school(),
            'levictas.contact.email'    => self::email(),
            'levictas.contact.address'  => self::address(),
        ], fn ($v) => self::isPlaceholder($v)));
    }
}
