<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Placeholder – implemented in milestone M3 (deadline reminders via push and mail,
 * idempotent through the sent_reminders unique index).
 */
#[Signature('reminders:dispatch')]
#[Description('Send due deadline reminders (implemented in M3)')]
class DispatchReminders extends Command
{
    public function handle(): int
    {
        $this->info('Reminders are not implemented yet (M3).');

        return self::SUCCESS;
    }
}
