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
        'linkedin' => '',
        'x'        => '',
        'github'   => '',
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
        'team'   => '[TEAM MEMBER NAMES]',
        // e.g. "the Polytechnic University of the Philippines"
        'school' => '[SCHOOL NAME]',
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
        'email'   => '[TEAM EMAIL]',
        'address' => '[SCHOOL ADDRESS]',
    ],

];
