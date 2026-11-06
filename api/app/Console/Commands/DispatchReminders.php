<?php

namespace App\Console\Commands;

use App\Services\Reminders\ReminderDispatcher;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Runs daily at 07:00 (routes/console.php). Safe to run more than once a day:
 * the sent_reminders log prevents duplicates. Messages are queued and sent by
 * the per-minute queue:work run.
 */
#[Signature('reminders:dispatch')]
#[Description('Queue due deadline and charge reminders (mail + push)')]
class DispatchReminders extends Command
{
    public function handle(ReminderDispatcher $dispatcher): int
    {
        $stats = $dispatcher->dispatch();

        $this->info("Reminders queued: {$stats['mails']} mail(s), {$stats['pushes']} push message(s) for {$stats['users']} user(s).");

        return self::SUCCESS;
    }
}
