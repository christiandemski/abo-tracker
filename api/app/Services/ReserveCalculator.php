<?php

namespace App\Services;

use App\Http\Resources\CategoryResource;
use App\Models\Subscription;
use App\Support\Fraction;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Reserve for subscriptions charged every 3 months or less often.
 *
 * - Monthly reserve = sum of their yearly costs / 12.
 * - Start buffer: simulate the next 12 months (each month + reserve, minus the charges
 *   due that month). The lowest negative balance, rounded up to 10 €, is the buffer.
 */
class ReserveCalculator
{
    public const MONTHS = 12;

    public const BUFFER_STEP_CENTS = 1000;

    public const UPCOMING_LIMIT = 4;

    public function __construct(
        private readonly BillingCalculator $billing,
        private readonly ChargeSchedule $schedule,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(?CarbonImmutable $today = null): array
    {
        $today ??= CarbonImmutable::today();

        /** @var Collection<int, Subscription> $long */
        $long = Subscription::query()->with(['category', 'priceChanges'])->get()
            ->filter(fn (Subscription $s) => $s->isActive() && $this->billing->isLongInterval($s->interval_unit, $s->interval_count))
            ->values();

        $yearly = Fraction::sum($long->map(fn (Subscription $s) => $this->billing->monthlyFor($s)->multiply(12)));
        $monthly = $yearly->multiply(1, 12);

        $simulation = $this->simulate($long, $monthly, $today->startOfMonth()->addMonthNoOverflow());

        return [
            'monthly_cents' => $monthly->round(),
            'yearly_cents' => $yearly->round(),
            'subscription_count' => $long->count(),
            'buffer_cents' => $simulation['buffer_cents'],
            'months' => $simulation['months'],
            'upcoming' => $this->upcoming($long, $today),
        ];
    }

    /**
     * @param  Collection<int, Subscription>  $long
     * @return array{buffer_cents: int, months: list<array<string, mixed>>}
     */
    public function simulate(Collection $long, Fraction $monthly, CarbonImmutable $start): array
    {
        $balance = Fraction::zero();
        $lowest = Fraction::zero();
        $months = [];

        for ($i = 0; $i < self::MONTHS; $i++) {
            $from = $start->addMonthsNoOverflow($i);
            $to = $from->endOfMonth()->startOfDay();

            $payments = [];
            foreach ($long as $subscription) {
                foreach ($this->schedule->between($subscription, $from, $to) as $charge) {
                    $payments[] = ['name' => $subscription->name, 'date' => $charge['date']->toDateString(), 'amount_cents' => $charge['amount_cents']];
                }
            }
            $paid = array_sum(array_column($payments, 'amount_cents'));

            $balance = $balance->add($monthly)->subtract(new Fraction($paid));
            if ($balance->compare($lowest) < 0) {
                $lowest = $balance;
            }

            $months[] = [
                'month' => $from->format('Y-m'),
                'balance_cents' => $balance->round(),
                'payments_cents' => $paid,
                'payments' => $payments,
            ];
        }

        $buffer = 0;
        if ($lowest->isNegative()) {
            $cents = $lowest->multiply(-1)->ceil();
            $buffer = intdiv($cents + self::BUFFER_STEP_CENTS - 1, self::BUFFER_STEP_CENTS) * self::BUFFER_STEP_CENTS;
        }

        return ['buffer_cents' => $buffer, 'months' => $months];
    }

    /**
     * Next charges of the long-interval subscriptions, from today.
     *
     * @param  Collection<int, Subscription>  $long
     * @return list<array<string, mixed>>
     */
    private function upcoming(Collection $long, CarbonImmutable $today): array
    {
        $items = [];
        foreach ($long as $subscription) {
            $charges = $this->schedule->between($subscription, $today, $today->addYear());
            if ($charges === []) {
                continue;
            }
            $items[] = [
                'subscription_id' => $subscription->id,
                'name' => $subscription->name,
                'date' => $charges[0]['date']->toDateString(),
                'amount_cents' => $charges[0]['amount_cents'],
                'category' => $subscription->category ? CategoryResource::make($subscription->category)->resolve() : null,
            ];
        }

        usort($items, fn (array $a, array $b) => [$a['date'], $a['name']] <=> [$b['date'], $b['name']]);

        return array_slice($items, 0, self::UPCOMING_LIMIT);
    }
}
