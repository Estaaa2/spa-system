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

    /*
    |--------------------------------------------------------------------------
    | Late clock-in review (clock-in guard, Nov 16)
    |--------------------------------------------------------------------------
    | A self clock-in this many minutes or more after the shift start is
    | flagged for roles with "edit attendance": late, or forgot to clock in?
    */
    'review_after_minutes' => (int) env('ATTENDANCE_REVIEW_AFTER_MINUTES', 30),
];
