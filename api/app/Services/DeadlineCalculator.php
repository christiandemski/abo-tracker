<?php

namespace App\Services;

use App\Enums\NoticeUnit;
use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use Carbon\CarbonImmutable;

/**
 * Cancellation deadlines.
 *
 * 1. Term end = start + minimum term; afterwards in steps of the renewal period.
 * 2. cancel_by = term end − notice period.
 *    We pick the first term whose cancel_by is today or later, i.e. the next deadline you can still meet.
 * 3. Without a minimum term ("jederzeit kündbar") there is no cancel_by date.
 *
 * Missing renewal period: contracts then renew monthly (German law for contracts
 * concluded since 03/2022, § 309 Nr. 9 BGB).
 */
class DeadlineCalculator
{
    public const DEFAULT_RENEWAL_MONTHS = 1;

    /** Safety stop for pathological input (≈ 100 years of monthly renewals). */
    private const MAX_STEPS = 1200;

    /**
     * @return array{term_end: ?CarbonImmutable, cancel_by: ?CarbonImmutable}
     */
    public function compute(
        ?CarbonImmutable $startDate,
        ?int $minTermMonths,
        ?int $renewalMonths,
        ?int $noticeValue,
        ?NoticeUnit $noticeUnit,
        CarbonImmutable $today,
    ): array {
        if ($startDate === null || $minTermMonths === null || $minTermMonths < 1) {
            return ['term_end' => null, 'cancel_by' => null];
        }

        $start = $startDate->startOfDay();
        $today = $today->startOfDay();
        $renewal = max(1, $renewalMonths ?? self::DEFAULT_RENEWAL_MONTHS);

        // Jump close to today first, then step forward (always computed from the start date).
        $k = 0;
        $elapsed = (int) floor($start->diffInMonths($today));
        if ($elapsed > $minTermMonths) {
            $k = max(0, intdiv($elapsed - $minTermMonths, $renewal) - 1);
        }

        for ($i = 0; $i < self::MAX_STEPS; $i++, $k++) {
            $termEnd = $start->addMonthsNoOverflow($minTermMonths + $k * $renewal);
            $cancelBy = $this->subtractNotice($termEnd, $noticeValue, $noticeUnit);

            if ($cancelBy->greaterThanOrEqualTo($today)) {
                return ['term_end' => $termEnd, 'cancel_by' => $cancelBy];
            }
        }

        return ['term_end' => null, 'cancel_by' => null];
    }

    /**
     * @return array{term_end: ?CarbonImmutable, cancel_by: ?CarbonImmutable}
     */
    public function computeFor(Subscription $subscription, CarbonImmutable $today): array
    {
        if ($subscription->status === SubscriptionStatus::Cancelled) {
            return ['term_end' => null, 'cancel_by' => null];
        }

        return $this->compute(
            $subscription->start_date,
            $subscription->min_term_months,
            $subscription->renewal_months,
            $subscription->notice_value,
            $subscription->notice_unit,
            $today,
        );
    }

    public function subtractNotice(CarbonImmutable $date, ?int $value, ?NoticeUnit $unit): CarbonImmutable
    {
        if ($value === null || $value <= 0 || $unit === null) {
            return $date;
        }

        return match ($unit) {
            NoticeUnit::Day => $date->subDays($value),
            NoticeUnit::Week => $date->subWeeks($value),
            NoticeUnit::Month => $date->subMonthsNoOverflow($value),
        };
    }

    /** German label: "14 Tage", "1 Monat", "3 Monate" … */
    public function noticeLabel(?int $value, ?NoticeUnit $unit): ?string
    {
        if ($value === null || $unit === null || $value <= 0) {
            return null;
        }

        return match ($unit) {
            NoticeUnit::Day => $value === 1 ? '1 Tag' : "{$value} Tage",
            NoticeUnit::Week => $value === 1 ? '1 Woche' : "{$value} Wochen",
            NoticeUnit::Month => $value === 1 ? '1 Monat' : "{$value} Monate",
        };
    }
}
