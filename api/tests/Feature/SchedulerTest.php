<?php

use Illuminate\Console\Scheduling\Schedule;

it('schedules the queue worker every minute and reminders at 07:00 Berlin time', function () {
    $events = collect(app(Schedule::class)->events());

    $queue = $events->first(fn ($e) => str_contains($e->command, 'queue:work'));
    $reminders = $events->first(fn ($e) => str_contains($e->command, 'reminders:dispatch'));

    expect($queue->expression)->toBe('* * * * *')
        ->and($queue->command)->toContain('--stop-when-empty')
        ->and($reminders->expression)->toBe('0 7 * * *')
        ->and($reminders->timezone)->toBe('Europe/Berlin');
});
