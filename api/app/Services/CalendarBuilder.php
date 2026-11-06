<?php

namespace App\Services;

use App\Http\Resources\CategoryResource;
use App\Models\Subscription;
use Carbon\CarbonImmutable;

/**
 * Month view: charges per day, month total and what is still open from today.
 */
class CalendarBuilder
{
    /** Day color levels in the month grid: below 30 €, 30–100 €, above 100 € */
    public const LEVEL_MEDIUM_CENTS = 3000;

    public const LEVEL_HIGH_CENTS = 10000;

    public function __construct(
        private readonly ChargeSchedule $schedule,
        private readonly BillingCalculator $billing,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(CarbonImmutable $month, ?CarbonImmutable $today = null): array
    {
        $today ??= CarbonImmutable::today();
        $from = $month->startOfMonth();
        $to = $month->endOfMonth()->startOfDay();

        $subscriptions = Subscription::query()->with(['category', 'priceChanges'])->get();

        /** @var array<string, list<array<string, mixed>>> $byDay */
        $byDay = [];
        foreach ($subscriptions as $subscription) {
            foreach ($this->schedule->between($subscription, $from, $to) as $charge) {
                $byDay[$charge['date']->toDateString()][] = $this->item($subscription, $charge['amount_cents']);
            }
        }
        ksort($byDay);

        $total = 0;
        $open = 0;
        $days = [];
        foreach ($byDay as $date => $items) {
            usort($items, fn (array $a, array $b) => [$b['amount_cents'], $a['name']] <=> [$a['amount_cents'], $b['name']]);
            $sum = array_sum(array_column($items, 'amount_cents'));
            $total += $sum;
            if ($date >= $today->toDateString()) {
                $open += $sum;
            }
            $days[] = [
                'date' => $date,
                'total_cents' => $sum,
                'level' => $this->level($sum),
                'items' => $items,
            ];
        }

        return [
            'month' => $from->format('Y-m'),
            'first_day' => $from->toDateString(),
            'days_in_month' => $from->daysInMonth,
            'today' => $today->toDateString(),
            'total_cents' => $total,
            'open_cents' => $open,
            'days' => $days,
        ];
    }

    public function level(int $cents): int
    {
        return match (true) {
            $cents > self::LEVEL_HIGH_CENTS => 3,
            $cents >= self::LEVEL_MEDIUM_CENTS => 2,
            default => 1,
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function item(Subscription $s, int $amountCents): array
    {
        return [
            'subscription_id' => $s->id,
            'name' => $s->name,
            'amount_cents' => $amountCents,
            'rhythm_label' => $this->billing->rhythmLabel($s->interval_unit, $s->interval_count),
            'status' => $s->status->value,
            // "kündbar bis" only where there is a real deadline
            'cancel_by_date' => $s->hasTerm() && $s->isActive() ? $s->cancel_by_date?->toDateString() : null,
            'category' => $s->category ? CategoryResource::make($s->category)->resolve() : null,
        ];
    }
}
