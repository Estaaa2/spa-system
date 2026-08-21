<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('deployments:process')->dailyAt('00:05');
Schedule::command('attendance:finalize-day')->dailyAt('23:55');
Schedule::command('subscriptions:send-expiry-reminders')->daily();
Schedule::command('bookings:mark-completed')->everyFifteenMinutes();
