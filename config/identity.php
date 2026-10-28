<?php

/*
|--------------------------------------------------------------------------
| Staff identity verification
|--------------------------------------------------------------------------
|
| Settings for the profile photo + ID + selfie verification that staff in
| the treatment-provider pool must pass before they can be assigned online
| home-service bookings. See claude/staff-verification-phases.md.
|
*/

return [

    /*
     * Accepted Philippine government IDs (primary IDs). The key is stored in
     * identity_verifications.id_type; the label is shown to staff and admins.
     */
    'id_types' => [
        'philid'          => 'PhilSys National ID (PhilID or printed ePhilID)',
        'passport'        => 'Philippine Passport',
        'drivers_license' => "Driver's License (LTO)",
        'prc'             => 'PRC Professional ID',
        'umid'            => 'UMID',
        'postal'          => 'Postal ID (PHLPost)',
    ],

    /*
     * Version of the consent notice the staff member agreed to. Change it
     * whenever the notice text changes, so each submission records which
     * notice was shown.
     */
    'consent_version' => '2026-10-28',

    /*
     * Disk for every file in this feature. "local" is storage/app/private,
     * which has no public URL. Files are only streamed by authorized
     * controllers.
     */
    'disk' => 'local',

    'directories' => [
        'verifications'  => 'identity-verifications',
        'profile_photos' => 'profile-photos',
    ],

    /*
     * Upload rules. Accepted types are the ones PHP GD can decode; iPhone
     * HEIC photos are converted to JPEG by the browser's file picker.
     */
    'upload' => [
        'max_kb'          => 5120,
        'mimes'           => ['jpg', 'jpeg', 'png', 'webp'],
        'max_megapixels'  => 40,   // refuse larger images before decoding (memory)
        'min_side_px'     => 300,  // too small to review
    ],

    /*
     * Re-encoding. Every upload is decoded and saved again as JPEG, which
     * drops EXIF data (including GPS location) and anything hidden in the file.
     */
    'output' => [
        'verification_max_side' => 1600,
        'profile_photo_max_side' => 800,
        'jpeg_quality' => 85,
    ],

    /*
     * Files left on disk that no row points to (failed deletes, removed test
     * data) are deleted by identity:purge once they are at least this old.
     */
    'orphan_grace_minutes' => 60,

];
