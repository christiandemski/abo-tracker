<?php

namespace App\Services\Reminders;

use App\Models\Subscription;
use Carbon\CarbonImmutable;

/**
 * One reminder that is due today for a subscription.
 */
final readonly class DueReminder
{
    public const TYPE_DEADLINE = 'deadline';

    public const TYPE_CHARGE = 'charge';

    public function __construct(
        public string $type,
        public Subscription $subscription,
        /** cancel-by date (deadline) or charge date (charge) */
        public CarbonImmutable $dueDate,
        /** offset that triggered the reminder */
        public int $offsetDays,
        /** days from today until the due date */
        public int $daysLeft,
        /** further offsets that matched in the same run (catch-up) and are logged as handled */
        public array $alsoCovers = [],
    ) {}

    public function isDeadline(): bool
    {
        return $this->type === self::TYPE_DEADLINE;
    }
}
