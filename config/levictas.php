<?php

/*
|--------------------------------------------------------------------------
| Levictas public-site settings
|--------------------------------------------------------------------------
|
| Values shown on the public pages (landing page and the public layout) and
| in the legal pages. These are public details, so they are versioned here
| rather than read from .env (landing footer Phase 1, Q5-A).
|
| After editing, run: php artisan config:clear  (or config:cache on the VPS)
|
*/

return [

    /*
    | Footer social links. Leave a value empty to hide that icon.
    | GitHub should point to the team's profile or organization, not the
    | production repository, unless that repository is meant to be public.
    */
    'social' => [
        'linkedin' => 'https://www.linkedin.com/in/levictas-development-8aa550449',
        'x'        => 'https://x.com/LevOff25',
        'github'   => 'https://github.com/levictasdevelopment',
    ],

    /*
    | Who runs Levictas, as named in the Terms and Conditions and the Privacy
    | Policy (landing footer Phase 2, D3 / Q4-A).
    |
    | TODO before the defense: replace every [BRACKETED] value. Anything still
    | in brackets is shown on the public pages exactly as written.
    */
    'operator' => [
        // e.g. "Juan Dela Cruz, Maria Santos and Jose Reyes"
        'team'   => 'Marjo Catibod, Piolo Lingo, Josh Wayne Melia, Princess Tamie Chan, and John Edward Dolor',
        // e.g. "the Polytechnic University of the Philippines"
        'school' => 'National College of Science and Technology',
    ],

    /*
    | Contact details on the legal pages (and the Contact page in Phase 3).
    | The email is also the privacy contact: the Privacy Policy and the staff
    | ID-verification consent notice both show it (Phase 2, Q2-A). It replaces
    | the old IDENTITY_PRIVACY_CONTACT .env value, which is no longer read.
    |
    | PayMongo requires a Philippine physical address on the site.
    */
    'contact' => [
        'email'   => 'levictas.business@gmail.com',
        'address' => 'National College of Science and Technology, Dasmariñas, Cavite, Philippines',
    ],

    /*
    | The Android app offered on /get-the-app (mobile app Phase 2, D2).
    |
    | The APK is NOT in git. Upload it to storage/app/mobile/<file> on the
    | server and set these in .env. A new version = upload the new file and
    | change these lines; no code change. The file size is read from the file.
    | If the file is missing, the page shows "not available right now".
    */
    'mobile_app' => [
        'file'        => env('MOBILE_APP_FILE', 'levictas.apk'),
        'version'     => env('MOBILE_APP_VERSION', ''),
        'released'    => env('MOBILE_APP_RELEASED', ''),   // YYYY-MM-DD
        'min_android' => env('MOBILE_APP_MIN_ANDROID', '7.0'),
        'sha256'      => env('MOBILE_APP_SHA256', ''),
    ],

];
