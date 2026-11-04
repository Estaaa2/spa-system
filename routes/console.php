<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('deployments:process')->dailyAt('00:05');
Schedule::command('attendance:finalize-day')->dailyAt('23:55');
Schedule::command('subscriptions:send-expiry-reminders')->daily();
Schedule::command('subscriptions:expire-pending')->hourly();
Schedule::command('subscriptions:send-trial-reminders')->daily();
Schedule::command('bookings:mark-completed')->everyFifteenMinutes();
Schedule::command('documents:send-expiry-reminders')->daily();
// Deletes leftover ID/selfie files and unused profile photos (identity verification, D9).
Schedule::command('identity:purge')->dailyAt('03:10');
