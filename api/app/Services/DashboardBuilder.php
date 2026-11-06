<?php

namespace App\Services;

use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Models\PriceChange;
use App\Models\Subscription;
use App\Support\Fraction;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Overview numbers. Sums are exact and rounded once per displayed value.
 */
class DashboardBuilder
{
    /** Deadlines shown in "Demnächst" this many days ahead */
    public const DEADLINE_DAYS = 30;

    /** Charges shown in "Demnächst" this many days ahead */
    public const CHARGE_DAYS = 14;

    public const UPCOMING_LIMIT = 8;

    public const BIGGEST_LIMIT = 5;

    public function __construct(private readonly BillingCalculator $billing) {}

    /**
     * @return array<string, mixed>
     */
    public function build(string $period, ?CarbonImmutable $today = null): array
    {
        $today ??= CarbonImmutable::today();
        $factor = $period === 'year' ? 12 : 1;

        /** @var Collection<int, Subscription> $all */
        $all = Subscription::query()->with('category')->get();
        $active = $all->filter(fn (Subscription $s) => $s->isActive())->values();

        $total = $this->billing->monthlyTotal($active)->multiply($factor);

        return [
            'period' => $period,
            'total_cents' => $total->round(),
            'subscription_count' => $active->count(),
            'converted_count' => $active->filter(
                fn (Subscription $s) => $this->billing->isLongInterval($s->interval_unit, $s->interval_count)
            )->count(),
            'inactive_count' => $all->count() - $active->count(),
            // Badge on the bell: price changes waiting for a decision
            'notices_count' => PriceChange::query()->where('status', 'pending')->whereHas('subscription')->count(),
            'categories' => $this->categories($active, $total, $factor),
            'upcoming' => $this->upcoming($active, $today),
            'biggest' => $this->biggest($active, $factor),
        ];
    }

    /**
     * @param  Collection<int, Subscription>  $active
     * @return list<array<string, mixed>>
     */
    private function categories(Collection $active, Fraction $total, int $factor): array
    {
        $groups = $active->groupBy(fn (Subscription $s) => $s->category_id ?? 0);

        $rows = $groups->map(function (Collection $subs, int $categoryId) use ($total, $factor) {
            $sum = $this->billing->monthlyTotal($subs)->multiply($factor);
            /** @var ?Category $category */
            $category = $subs->first()->category;

            return [
                'category' => $category ? CategoryResource::make($category)->resolve() : null,
                'label' => $category?->name ?? 'Ohne Kategorie',
                'total_cents' => $sum->round(),
                // Share of the total, 0…1, for the stacked bar (display only)
                'share' => $total->isZero() ? 0.0 : round(
                    ($sum->numerator * $total->denominator) / ($sum->denominator * $total->numerator), 4,
                ),
                'count' => $subs->count(),
                '_sort' => [$sum->numerator / $sum->denominator, $categoryId],
            ];
        });

        return $rows
            ->sortByDesc('_sort')
            ->map(fn (array $row) => array_diff_key($row, ['_sort' => true]))
            ->values()
            ->all();
    }

    /**
     * Deadlines first (orange in the app, only for subscriptions with a term),
     * then the next charges.
     *
     * @param  Collection<int, Subscription>  $active
     * @return list<array<string, mixed>>
     */
    private function upcoming(Collection $active, CarbonImmutable $today): array
    {
        $deadlineUntil = $today->addDays(self::DEADLINE_DAYS);
        $chargeUntil = $today->addDays(self::CHARGE_DAYS);

        $deadlines = $active
            ->filter(fn (Subscription $s) => $s->hasTerm()
                && ! $s->isKeptForCurrentTerm()
                && $s->cancel_by_date !== null
                && $s->cancel_by_date->betweenIncluded($today, $deadlineUntil))
            ->sortBy(fn (Subscription $s) => $s->cancel_by_date->toDateString())
            ->map(fn (Subscription $s) => $this->item('deadline', $s->cancel_by_date, $s, $today));

        $deadlineIds = $deadlines->pluck('subscription.id')->all();

        $charges = $active
            ->filter(fn (Subscription $s) => ! in_array($s->id, $deadlineIds, true)
                && $s->next_charge_date !== null
                && $s->next_charge_date->betweenIncluded($today, $chargeUntil))
            ->sortBy(fn (Subscription $s) => [$s->next_charge_date->toDateString(), mb_strtolower($s->name)])
            ->map(fn (Subscription $s) => $this->item('charge', $s->next_charge_date, $s, $today));

        return $deadlines->concat($charges)->take(self::UPCOMING_LIMIT)->values()->all();
    }

    /**
     * @param  Collection<int, Subscription>  $active
     * @return list<array<string, mixed>>
     */
    private function biggest(Collection $active, int $factor): array
    {
        return $active
            ->sortByDesc(function (Subscription $s) {
                $m = $this->billing->monthlyFor($s);

                return $m->numerator / $m->denominator;
            })
            ->take(self::BIGGEST_LIMIT)
            ->map(fn (Subscription $s) => [
                'subscription' => $this->compact($s),
                'period_cents' => $this->billing->monthlyFor($s)->multiply($factor)->round(),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function item(string $type, CarbonImmutable $date, Subscription $s, CarbonImmutable $today): array
    {
        return [
            'type' => $type,
            'date' => $date->toDateString(),
            'days' => (int) $today->diffInDays($date),
            'subscription' => $this->compact($s),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function compact(Subscription $s): array
    {
        return [
            'id' => $s->id,
            'name' => $s->name,
            'amount_cents' => $s->amount_cents,
            'monthly_cents' => $this->billing->monthlyFor($s)->round(),
            'rhythm_label' => $this->billing->rhythmLabel($s->interval_unit, $s->interval_count),
            'next_charge_date' => $s->next_charge_date?->toDateString(),
            'term_end_date' => $s->term_end_date?->toDateString(),
            'cancel_by_date' => $s->cancel_by_date?->toDateString(),
            'has_term' => $s->hasTerm(),
            'category' => $s->category ? CategoryResource::make($s->category)->resolve() : null,
        ];
    }
}
