<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Online minimum notice (attendance-aware booking Phase 3, D7)
    |--------------------------------------------------------------------------
    | A same-day ONLINE booking (web and mobile: slot grid and checkout) must
    | start at least this many minutes from now. A slot exactly this far away is
    | allowed. Staff bookings and walk-ins are not affected.
    |
    | After editing, run: php artisan config:clear  (or config:cache on the VPS)
    */
    'online_min_notice_minutes' => (int) env('BOOKING_ONLINE_MIN_NOTICE_MINUTES', 30),
];
