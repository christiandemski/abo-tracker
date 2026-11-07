<?php

namespace App\Services;

use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use App\Support\Fraction;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Monthly fixed costs of the past months, reconstructed from the data we have
 * (no snapshots):
 *
 * - A subscription counts from its start_date on; without one it is assumed to have always existed.
 * - Cancelled ones count up to their end (ends_at, else the day of cancellation),
 *   installments up to their last payment.
 * - Savings are not fixed costs and are left out.
 * - Paused ones are left out (we do not know since when).
 * - Prices follow the accepted price changes (PriceTimeline).
 * - The current month equals the overview total (active subscriptions, current price).
 */
class HistoryBuilder
{
    public const MAX_MONTHS = 24;

    public function __construct(
        private readonly BillingCalculator $billing,
        private readonly PriceTimeline $prices,
        private readonly ChargeSchedule $schedule,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(int $months, ?CarbonImmutable $today = null): array
    {
        $today ??= CarbonImmutable::today();
        /** @var Collection<int, Subscription> $all */
        $all = Subscription::query()->with('priceChanges')->get()
            ->reject(fn (Subscription $s) => $s->isSavings())
            ->values();

        $current = $this->billing->monthlyTotal($all->filter(fn (Subscription $s) => $s->isActive()))->round();

        $rows = [];
        for ($i = $months - 1; $i >= 1; $i--) {
            $month = $today->startOfMonth()->subMonthsNoOverflow($i);
            $rows[] = ['month' => $month->format('Y-m'), 'total_cents' => $this->totalAt($all, $month->endOfMonth()->startOfDay())];
        }
        $rows[] = ['month' => $today->format('Y-m'), 'total_cents' => $current];

        $previous = count($rows) > 1 ? $rows[count($rows) - 2]['total_cents'] : null;

        return [
            'months' => $rows,
            'current_cents' => $current,
            'change_cents' => $previous === null ? null : $current - $previous,
        ];
    }

    /**
     * @param  Collection<int, Subscription>  $all
     */
    public function totalAt(Collection $all, CarbonImmutable $date): int
    {
        $parts = [];
        foreach ($all as $s) {
            if (! $this->runsOn($s, $date)) {
                continue;
            }
            $parts[] = $this->billing->monthly($this->prices->priceOn($s, $date), $s->interval_unit, $s->interval_count);
        }

        return Fraction::sum($parts)->round();
    }

    private function runsOn(Subscription $s, CarbonImmutable $date): bool
    {
        if ($s->status === SubscriptionStatus::Paused) {
            return false;
        }
        if ($s->start_date !== null && $s->start_date->greaterThan($date)) {
            return false;
        }
        $end = $this->schedule->endOf($s);

        return $end === null || $end->greaterThanOrEqualTo($date);
    }
}
