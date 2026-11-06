<?php

namespace App\Services;

use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use Carbon\CarbonImmutable;

/**
 * Concrete charges (date + price on that date) of a subscription in a period.
 * Shared by the month view, the reserve plan and the iCal feed.
 *
 * - Paused subscriptions do not charge.
 * - Cancelled ones charge until their end (ends_at, or the day of cancellation if unknown).
 */
class ChargeSchedule
{
    public function __construct(
        private readonly BillingCalculator $billing,
        private readonly PriceTimeline $prices,
    ) {}

    public function charges(Subscription $subscription): bool
    {
        return $subscription->status !== SubscriptionStatus::Paused;
    }

    /**
     * @return list<array{date: CarbonImmutable, amount_cents: int}>
     */
    public function between(Subscription $subscription, CarbonImmutable $from, CarbonImmutable $to): array
    {
        if (! $this->charges($subscription)) {
            return [];
        }

        $end = $this->endOf($subscription);
        if ($end !== null && $end->lessThan($to)) {
            $to = $end;
        }
        if ($to->lessThan($from)) {
            return [];
        }

        return array_map(
            fn (CarbonImmutable $date) => ['date' => $date, 'amount_cents' => $this->prices->priceOn($subscription, $date)],
            $this->billing->chargesBetween($subscription, $from->startOfDay(), $to->startOfDay()),
        );
    }

    /** Last day the subscription may charge, null = open-ended. */
    public function endOf(Subscription $subscription): ?CarbonImmutable
    {
        if ($subscription->ends_at !== null) {
            return $subscription->ends_at;
        }

        return $subscription->status === SubscriptionStatus::Cancelled
            ? ($subscription->cancelled_at ?? CarbonImmutable::minValue())
            : null;
    }
}
