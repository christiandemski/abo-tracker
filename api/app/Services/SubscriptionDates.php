<?php

namespace App\Services;

use App\Models\Subscription;
use Carbon\CarbonImmutable;

/**
 * Keeps the stored, derived dates (next charge, term end, cancel-by) up to date.
 * Runs on every save and daily in the scheduler (subscriptions:refresh-dates).
 */
class SubscriptionDates
{
    public function __construct(
        private readonly BillingCalculator $billing,
        private readonly DeadlineCalculator $deadlines,
    ) {}

    public function apply(Subscription $subscription, ?CarbonImmutable $today = null): void
    {
        $today ??= CarbonImmutable::today();

        // Without an explicit start, a minimum term starts with the first charge.
        if ($subscription->min_term_months !== null && $subscription->start_date === null) {
            $subscription->start_date = $subscription->anchor_date;
        }

        $subscription->next_charge_date = $this->billing->nextChargeFor($subscription, $today);

        ['term_end' => $termEnd, 'cancel_by' => $cancelBy] = $this->deadlines->computeFor($subscription, $today);
        $subscription->term_end_date = $termEnd;
        $subscription->cancel_by_date = $cancelBy;
    }
}
