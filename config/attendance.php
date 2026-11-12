<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Clock-in grace (staff scheduling Phase 4, Q4 A)
    |--------------------------------------------------------------------------
    | Staff may clock in from (shift start − grace) and are "late" after
    | (shift start + grace). One value for every branch.
    */
    'grace_minutes' => (int) env('ATTENDANCE_GRACE_MINUTES', 15),
];
