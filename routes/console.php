<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('deployments:process')->dailyAt('00:05');
Schedule::command('subscriptions:send-expiry-reminders')->daily();
