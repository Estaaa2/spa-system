<?php

namespace App\Support;

use Carbon\Carbon;

/**
 * The Android app offered on /get-the-app (mobile app Phase 2).
 * Reads config('levictas.mobile_app') and the APK file itself. No database,
 * so the page works even when the database doesn't.
 */
class MobileApp
{
    /** Where the APK lives on the server: storage/app/mobile/<file>. */
    public static function path(): string
    {
        // basename(): a value from .env can never point outside the folder.
        return storage_path('app/mobile/' . basename((string) config('levictas.mobile_app.file', 'levictas.apk')));
    }

    /** True when the file is on the server and a version is set. */
    public static function available(): bool
    {
        return self::version() !== '' && is_file(self::path());
    }

    public static function version(): string
    {
        return trim((string) config('levictas.mobile_app.version', ''));
    }

    /** "Levictas-1.0.0.apk" */
    public static function downloadName(): string
    {
        $version = preg_replace('/[^0-9A-Za-z.\-]/', '', self::version());

        return 'Levictas' . ($version !== '' ? '-' . $version : '') . '.apk';
    }

    /** "62.8 MB", or null when the file is missing. */
    public static function size(): ?string
    {
        if (! is_file(self::path())) {
            return null;
        }

        return number_format(filesize(self::path()) / 1048576, 1) . ' MB';
    }

    public static function minAndroid(): string
    {
        return trim((string) config('levictas.mobile_app.min_android', '7.0')) ?: '7.0';
    }

    /** "Nov 14, 2026", or null when unset or not a date. */
    public static function released(): ?string
    {
        $value = trim((string) config('levictas.mobile_app.released', ''));

        if ($value === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->format('M j, Y');
        } catch (\Throwable) {
            return null;
        }
    }

    /** Lower-case 64-character SHA-256, or null when unset or malformed. */
    public static function sha256(): ?string
    {
        $value = strtolower(trim((string) config('levictas.mobile_app.sha256', '')));

        return preg_match('/^[0-9a-f]{64}$/', $value) ? $value : null;
    }

    /**
     * The QR code is a fixed SVG made for https://levictas.com/get-the-app.
     * Only show it when this site really is at that domain, so a QR never
     * sends people somewhere else (localhost, or a different domain later).
     * If the domain changes, regenerate the SVG for the new address.
     */
    public static function showQr(): bool
    {
        $url = parse_url(route('mobile-app'));

        return strtolower($url['host'] ?? '') === 'levictas.com'
            && ($url['path'] ?? '') === '/get-the-app'
            && is_file(public_path('images/get-the-app-qr.svg'));
    }
}
