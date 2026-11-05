<?php

namespace App\Support\Verification;

use Carbon\Carbon;

/**
 * The one rule for confirming a verification document's expiration date.
 * Used by every admin approval screen (Registered Spas, Branch
 * Applications, Document Review) so they cannot drift apart.
 *
 * - BIR Certificate: never has an expiry.
 * - Business Permit: always has one; the administrator must enter it.
 * - Government ID, DTI / SEC: the administrator enters the date OR ticks
 *   "No expiration date" (PhilSys, UMID, Senior Citizen and TIN IDs and
 *   SEC certificates have none). A blank field is never read as
 *   "no expiry", so a forgotten date cannot slip through.
 */
final class DocumentExpiryRules
{
    /** Types that may be confirmed as having no expiration date. */
    public const NO_EXPIRY_ALLOWED = [
        'government_id',
        'dti_sec',
    ];

    public static function allowsNoExpiry(string $type): bool
    {
        return in_array($type, self::NO_EXPIRY_ALLOWED, true);
    }

    /** Validation rules for the two admin form fields. */
    public static function requestRules(): array
    {
        return [
            'document_expiry' => ['nullable', 'array'],
            'document_expiry.*' => ['nullable', 'date_format:Y-m-d'],
            'document_no_expiry' => ['nullable', 'array'],
            'document_no_expiry.*' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Checks one document and returns either an error message or the
     * attributes to save on approval.
     *
     * @return array{error: ?string, attributes: array<string, mixed>}
     */
    public static function confirm(
        string $type,
        string $label,
        ?string $expiry,
        bool $noExpiry,
        ?int $adminId
    ): array {
        if ($type === 'bir_certificate') {
            return self::ok([
                'expiry_date' => null,
                'expiry_date_raw' => null,
                'expiry_detection_status' => 'not_required',
                'expiry_detection_source' => null,
                'expiry_scanned_at' => null,
                'expiry_verified_at' => now(),
                'expiry_verified_by' => $adminId,
            ]);
        }

        if ($noExpiry && ! self::allowsNoExpiry($type)) {
            return self::error(
                "A {$label} always has an expiration date. Enter it before approving."
            );
        }

        if ($noExpiry) {
            return self::ok([
                'expiry_date' => null,
                'expiry_detection_status' => 'verified_no_expiry',
                'expiry_verified_at' => now(),
                'expiry_verified_by' => $adminId,
            ]);
        }

        if (! filled($expiry)) {
            return self::error(
                self::allowsNoExpiry($type)
                    ? "Confirm the {$label} expiration date, or tick \"No expiration date\", before approving."
                    : "Confirm the {$label} expiration date before approving."
            );
        }

        if (
            Carbon::createFromFormat('Y-m-d', $expiry)
                ->startOfDay()
                ->isBefore(today())
        ) {
            return self::error(
                "{$label} cannot be approved because its expiration date has already passed."
            );
        }

        return self::ok([
            'expiry_date' => $expiry,
            'expiry_detection_status' => 'verified',
            'expiry_verified_at' => now(),
            'expiry_verified_by' => $adminId,
        ]);
    }

    private static function ok(array $attributes): array
    {
        return ['error' => null, 'attributes' => $attributes];
    }

    private static function error(string $message): array
    {
        return ['error' => $message, 'attributes' => []];
    }
}
