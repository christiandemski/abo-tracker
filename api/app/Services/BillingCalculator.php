<?php

namespace App\Services;

use App\Enums\IntervalUnit;
use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use App\Support\Fraction;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * Converts amounts between rhythms and computes charge dates.
 *
 * monthly = amount × f / interval_count, f = 52/12 (week) · 1 (month) · 1/12 (year)
 * Charge dates are always anchor + n × interval, computed from the anchor
 * (addMonthsNoOverflow), never by adding up month after month.
 */
class BillingCalculator
{
    public function monthly(int $amountCents, IntervalUnit $unit, int $count = 1): Fraction
    {
        $this->assertCount($count);

        return match ($unit) {
            IntervalUnit::Week => new Fraction($amountCents * 52, 12 * $count),
            IntervalUnit::Month => new Fraction($amountCents, $count),
            IntervalUnit::Year => new Fraction($amountCents, 12 * $count),
        };
    }

    public function monthlyFor(Subscription $subscription): Fraction
    {
        return $this->monthly($subscription->amount_cents, $subscription->interval_unit, $subscription->interval_count);
    }

    public function yearly(int $amountCents, IntervalUnit $unit, int $count = 1): Fraction
    {
        return $this->monthly($amountCents, $unit, $count)->multiply(12);
    }

    /** Average cost per day of the year (365 days). */
    public function daily(int $amountCents, IntervalUnit $unit, int $count = 1): Fraction
    {
        return $this->yearly($amountCents, $unit, $count)->multiply(1, 365);
    }

    /**
     * Sum of the monthly costs of the given subscriptions (exact).
     *
     * @param  iterable<Subscription>  $subscriptions
     */
    public function monthlyTotal(iterable $subscriptions): Fraction
    {
        $parts = [];
        foreach ($subscriptions as $subscription) {
            $parts[] = $this->monthlyFor($subscription);
        }

        return Fraction::sum($parts);
    }

    /**
     * The n-th charge date (n = 0 is the anchor itself).
     */
    public function occurrence(CarbonImmutable $anchor, IntervalUnit $unit, int $count, int $n): CarbonImmutable
    {
        $this->assertCount($count);
        $steps = $n * $count;

        return match ($unit) {
            IntervalUnit::Week => $anchor->addWeeks($steps),
            IntervalUnit::Month => $anchor->addMonthsNoOverflow($steps),
            IntervalUnit::Year => $anchor->addYearsNoOverflow($steps),
        };
    }

    /**
     * First charge on or after $from. Null if the subscription ends before it.
     */
    public function nextCharge(
        CarbonImmutable $anchor,
        IntervalUnit $unit,
        int $count,
        CarbonImmutable $from,
        ?CarbonImmutable $endsAt = null,
    ): ?CarbonImmutable {
        $anchor = $anchor->startOfDay();
        $from = $from->startOfDay();

        if ($from->lessThanOrEqualTo($anchor)) {
            $next = $anchor;
        } else {
            // Estimate n from the distance, then correct by at most a step or two.
            $n = max(0, $this->estimateSteps($anchor, $unit, $count, $from) - 1);
            $next = $this->occurrence($anchor, $unit, $count, $n);
            while ($next->lessThan($from)) {
                $next = $this->occurrence($anchor, $unit, $count, ++$n);
            }
        }

        if ($endsAt !== null && $next->greaterThan($endsAt->startOfDay())) {
            return null;
        }

        return $next;
    }

    public function nextChargeFor(Subscription $subscription, CarbonImmutable $today): ?CarbonImmutable
    {
        if ($subscription->status === SubscriptionStatus::Paused) {
            return null;
        }

        return $this->nextCharge(
            $subscription->anchor_date,
            $subscription->interval_unit,
            $subscription->interval_count,
            $today,
            $subscription->ends_at,
        );
    }

    /**
     * All charges in [$from, $to] (inclusive), e.g. for calendars.
     *
     * @return list<CarbonImmutable>
     */
    public function chargesBetween(Subscription $subscription, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $dates = [];
        $next = $this->nextCharge(
            $subscription->anchor_date, $subscription->interval_unit, $subscription->interval_count, $from, $subscription->ends_at,
        );
        if ($next === null) {
            return [];
        }

        $n = $this->estimateSteps($subscription->anchor_date, $subscription->interval_unit, $subscription->interval_count, $next);
        // Find the exact index of $next.
        while ($n > 0 && $this->occurrence($subscription->anchor_date, $subscription->interval_unit, $subscription->interval_count, $n)->greaterThan($next)) {
            $n--;
        }
        while ($this->occurrence($subscription->anchor_date, $subscription->interval_unit, $subscription->interval_count, $n)->lessThan($next)) {
            $n++;
        }

        $date = $next;
        while ($date->lessThanOrEqualTo($to)) {
            if ($subscription->ends_at !== null && $date->greaterThan($subscription->ends_at)) {
                break;
            }
            $dates[] = $date;
            $date = $this->occurrence($subscription->anchor_date, $subscription->interval_unit, $subscription->interval_count, ++$n);
        }

        return $dates;
    }

    /**
     * German label for a rhythm: "monatlich", "jährlich", "alle 2 Monate" …
     */
    public function rhythmLabel(IntervalUnit $unit, int $count = 1): string
    {
        return match (true) {
            $unit === IntervalUnit::Week && $count === 1 => 'wöchentlich',
            $unit === IntervalUnit::Week && $count === 2 => 'alle 2 Wochen',
            $unit === IntervalUnit::Week => "alle {$count} Wochen",
            $unit === IntervalUnit::Month && $count === 1 => 'monatlich',
            $unit === IntervalUnit::Month && $count === 3 => 'vierteljährlich',
            $unit === IntervalUnit::Month && $count === 6 => 'halbjährlich',
            $unit === IntervalUnit::Month && $count === 12 => 'jährlich',
            $unit === IntervalUnit::Month => "alle {$count} Monate",
            $unit === IntervalUnit::Year && $count === 1 => 'jährlich',
            default => "alle {$count} Jahre",
        };
    }

    /** True if the subscription is charged less often than monthly (≥ 3 months). */
    public function isLongInterval(IntervalUnit $unit, int $count): bool
    {
        return match ($unit) {
            IntervalUnit::Week => false,
            IntervalUnit::Month => $count >= 3,
            IntervalUnit::Year => true,
        };
    }

    private function estimateSteps(CarbonImmutable $anchor, IntervalUnit $unit, int $count, CarbonImmutable $target): int
    {
        $units = match ($unit) {
            IntervalUnit::Week => (int) floor($anchor->diffInDays($target) / 7),
            IntervalUnit::Month => (int) floor($anchor->diffInMonths($target)),
            IntervalUnit::Year => (int) floor($anchor->diffInYears($target)),
        };

        return max(0, intdiv(max(0, $units), $count));
    }

    private function assertCount(int $count): void
    {
        if ($count < 1) {
            throw new InvalidArgumentException('interval_count must be at least 1.');
        }
    }
}
