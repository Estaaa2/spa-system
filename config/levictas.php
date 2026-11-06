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
        'linkedin' => 'www.linkedin.com/in/levictas-development-8aa550449',
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

];
