<?php

use Illuminate\Support\Facades\Schedule;

/*
 * The webspace allows no long-running processes. A single cron job runs
 * `php artisan schedule:run` every minute; everything time-based starts here.
 */

// Work off the database queue, then exit. --max-time keeps runs below one minute.
Schedule::command('queue:work --stop-when-empty --max-time=50 --tries=3')
    ->everyMinute()
    ->withoutOverlapping(5);

// Recompute next charge and cancellation deadlines (dates move on every day)
Schedule::command('subscriptions:refresh-dates')
    ->dailyAt('00:05')
    ->timezone('Europe/Berlin')
    ->withoutOverlapping();

// Deadline reminders (M3)
Schedule::command('reminders:dispatch')
    ->dailyAt('07:00')
    ->timezone('Europe/Berlin')
    ->withoutOverlapping();

// Housekeeping
Schedule::command('auth:clear-resets')->daily();
Schedule::command('sanctum:prune-expired --hours=24')->daily();
