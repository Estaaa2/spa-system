<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('deployments:process')->dailyAt('00:05');
Schedule::command('attendance:finalize-day')->dailyAt('23:55');
Schedule::command('subscriptions:send-expiry-reminders')->daily();
Schedule::command('subscriptions:expire-pending')->hourly();
Schedule::command('subscriptions:send-trial-reminders')->daily();
// Pauses / restores staff when a plan's staff limit changes at a set time (trial end,
// a queued Basic period starting after Premium). Overview Rev 3, F-19.
Schedule::command('subscriptions:enforce-limits')->hourly();
// Moves open bookings reserved → pending by the clock. Never completes them:
// only staff checkout does (booking lifecycle fix, Phase 1, D1/Q5-B).
Schedule::command('bookings:sync-statuses')->everyFifteenMinutes();
Schedule::command('documents:send-expiry-reminders')->daily();
// Deletes leftover ID/selfie files and unused profile photos (identity verification, D9).
Schedule::command('identity:purge')->dailyAt('03:10');
// Decision Support: this morning's staffing / hiring / promo / expansion recommendations
// (DSS revamp, Q5 B). After deployments:process (00:05) so the day's staff changes count.
Schedule::command('dss:generate')->dailyAt('00:30');
// Every minute: proves to `php artisan demo:check` that the cron runs (demo safety Phase 1).
Schedule::call([\App\Services\Diagnostics\SchedulerHeartbeat::class, 'beat'])
    ->everyMinute()
    ->name('scheduler-heartbeat');
