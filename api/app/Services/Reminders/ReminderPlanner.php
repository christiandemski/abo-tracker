<?php

namespace App\Services\Reminders;

use App\Models\ReminderSetting;
use App\Models\Subscription;
use App\Services\BillingCalculator;
use App\Services\DeadlineCalculator;
use Carbon\CarbonImmutable;

/**
 * Decides which reminders are due today. Pure logic, no sending.
 *
 * Deadlines: a reminder is due when the cancel-by date is exactly one of the user's offsets away.
 * Missed runs (server down): reminder days in the last CATCH_UP_DAYS days are caught up,
 * as long as the deadline has not passed. If several offsets match at once, only the most
 * urgent (smallest) one is sent; the others are logged as covered.
 *
 * Charges ("Abbuchung morgen"): only for subscriptions charged less often than monthly.
 */
class ReminderPlanner
{
    public const CATCH_UP_DAYS = 2;

    public function __construct(
        private readonly DeadlineCalculator $deadlines,
        private readonly BillingCalculator $billing,
    ) {}

    /**
     * @param  iterable<Subscription>  $subscriptions  of one user
     * @return list<DueReminder>
     */
    public function plan(iterable $subscriptions, ReminderSetting $settings, CarbonImmutable $today): array
    {
        $today = $today->startOfDay();
        $due = [];

        foreach ($subscriptions as $subscription) {
            if (! $subscription->isActive()) {
                continue;
            }

            if ($deadline = $this->deadline($subscription, $settings->offsets(), $today)) {
                $due[] = $deadline;
            }

            if ($settings->charge_reminders && ($charge = $this->charge($subscription, $today))) {
                $due[] = $charge;
            }
        }

        usort($due, fn (DueReminder $a, DueReminder $b) => [$a->dueDate, $a->subscription->name] <=> [$b->dueDate, $b->subscription->name]);

        return $due;
    }

    /**
     * @param  list<int>  $offsets
     */
    private function deadline(Subscription $subscription, array $offsets, CarbonImmutable $today): ?DueReminder
    {
        if (! $subscription->remind || ! $subscription->hasTerm()) {
            return null;
        }

        // Always recompute: stored dates may be a day old if the nightly refresh did not run.
        $cancelBy = $this->deadlines->computeFor($subscription, $today)['cancel_by'];
        if ($cancelBy === null || $cancelBy->lessThan($today)) {
            return null;
        }

        // "Behalten" for this term: no reminder until the next one
        if ($subscription->kept_until !== null && $cancelBy->lessThan($subscription->kept_until)) {
            return null;
        }

        $matching = [];
        foreach ($offsets as $offset) {
            $reminderDay = $cancelBy->subDays($offset);
            if ($reminderDay->betweenIncluded($today->subDays(self::CATCH_UP_DAYS), $today)) {
                $matching[] = $offset;
            }
        }

        if ($matching === []) {
            return null;
        }

        sort($matching);
        $offset = array_shift($matching);

        return new DueReminder(
            DueReminder::TYPE_DEADLINE,
            $subscription,
            $cancelBy,
            $offset,
            (int) $today->diffInDays($cancelBy),
            $matching,
        );
    }

    private function charge(Subscription $subscription, CarbonImmutable $today): ?DueReminder
    {
        if (! $this->billing->isLongInterval($subscription->interval_unit, $subscription->interval_count)) {
            return null;
        }

        $tomorrow = $today->addDay();
        $next = $this->billing->nextChargeFor($subscription, $tomorrow);

        if ($next === null || ! $next->equalTo($tomorrow)) {
            return null;
        }

        return new DueReminder(DueReminder::TYPE_CHARGE, $subscription, $next, 1, 1);
    }
}
