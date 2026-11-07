<?php

namespace App\Services;

use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use Carbon\CarbonImmutable;

/**
 * Keeps the stored, derived dates (next charge, term end, cancel-by) up to date.
 * Runs on every save and daily in the scheduler (subscriptions:refresh-dates).
 *
 * Installments and savings have no term: their term fields are cleared. An installment
 * whose last payment (ends_at) has passed is finished; moving ends_at forward reopens it.
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

        if (! $subscription->isSubscription()) {
            $subscription->min_term_months = null;
            $subscription->renewal_months = null;
            $subscription->notice_value = null;
            $subscription->notice_unit = null;
            $subscription->kept_until = null;
        }

        if ($subscription->isInstallment()) {
            $this->applyInstallmentStatus($subscription, $today);
        } elseif ($subscription->status === SubscriptionStatus::Finished) {
            // Kind changed away from installment
            $subscription->status = SubscriptionStatus::Active;
        }

        // Without an explicit start, a minimum term starts with the first charge.
        if ($subscription->min_term_months !== null && $subscription->start_date === null) {
            $subscription->start_date = $subscription->anchor_date;
        }

        $subscription->next_charge_date = $this->billing->nextChargeFor($subscription, $today);

        ['term_end' => $termEnd, 'cancel_by' => $cancelBy] = $this->deadlines->computeFor($subscription, $today);
        $subscription->term_end_date = $termEnd;
        $subscription->cancel_by_date = $cancelBy;
    }

    private function applyInstallmentStatus(Subscription $subscription, CarbonImmutable $today): void
    {
        $paidOff = $subscription->ends_at !== null && $subscription->ends_at->lessThan($today->startOfDay());

        if ($paidOff && $subscription->status === SubscriptionStatus::Active) {
            $subscription->status = SubscriptionStatus::Finished;
        } elseif (! $paidOff && $subscription->status === SubscriptionStatus::Finished) {
            $subscription->status = SubscriptionStatus::Active;
        }
    }
}
