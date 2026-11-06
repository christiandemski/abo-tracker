<?php

namespace App\Services\Import;

use App\Enums\IntervalUnit;

/**
 * Finds recurring payments in a list of debits (SPEC "CSV-Import & Erkennung").
 *
 * 1. Direct debits with creditor ID + mandate reference are grouped first (same contract),
 *    the rest by normalized payee and similar amount (±10 %).
 * 2. Rhythm from the median gap: 26–35 days monthly, 85–95 quarterly, 175–190 half-yearly,
 *    355–375 yearly. 3+ bookings = confident, 2 = "Rhythmus prüfen".
 * 3. Single bookings only if they are direct debits with creditor ID and mandate reference
 *    (typical yearly contracts); they get "Rhythmus prüfen" and a yearly guess.
 * Everything else (one-off purchases, irregular payments) is dropped and never stored.
 */
class RecurringPaymentDetector
{
    public const AMOUNT_TOLERANCE = 0.10;

    /** Allowed deviation of single gaps (days) for payments without a mandate */
    public const GAP_SLACK = 3;

    /** [min days, max days, unit, count] */
    private const RHYTHMS = [
        [26, 35, IntervalUnit::Month, 1],
        [85, 95, IntervalUnit::Month, 3],
        [175, 190, IntervalUnit::Month, 6],
        [355, 375, IntervalUnit::Year, 1],
    ];

    public function __construct(private readonly PayeeNormalizer $normalizer) {}

    /**
     * @param  list<Transaction>  $debits
     * @return list<DetectedPayment>
     */
    public function detect(array $debits): array
    {
        $byContract = [];
        $byPayee = [];

        foreach ($debits as $t) {
            $payee = $this->normalizer->normalize($t->payee, $t->purpose);
            if ($payee === '') {
                continue;
            }
            if ($t->isDirectDebit() && ! $this->normalizer->isPaymentService($t->payee)) {
                $byContract[$t->creditorId.'|'.$t->mandateRef][] = [$payee, $t];
            } else {
                $byPayee[$payee][] = $t;
            }
        }

        $groups = [];

        // Same mandate = same contract, even if the amount changed (price change!).
        foreach ($byContract as $items) {
            $groups[] = [$items[0][0], array_map(fn ($i) => $i[1], $items), true];
        }

        foreach ($byPayee as $payee => $transactions) {
            foreach ($this->splitByAmount($transactions) as $cluster) {
                $groups[] = [$payee, $cluster, false];
            }
        }

        $detected = [];
        foreach ($groups as [$payee, $transactions, $isMandate]) {
            usort($transactions, fn (Transaction $a, Transaction $b) => $a->date <=> $b->date);
            $result = $this->classify($payee, $transactions, $isMandate);
            if ($result !== null) {
                $detected[] = $result;
            }
        }

        usort($detected, fn (DetectedPayment $a, DetectedPayment $b) => [$b->confidence === 'high', $a->payeeNormalized] <=> [$a->confidence === 'high', $b->payeeNormalized]);

        return $detected;
    }

    /**
     * @param  list<Transaction>  $transactions  sorted
     */
    private function classify(string $payee, array $transactions, bool $isMandate): ?DetectedPayment
    {
        $count = count($transactions);

        if ($count === 1) {
            return $isMandate
                ? new DetectedPayment($payee, $transactions, IntervalUnit::Year, 1, DetectedPayment::CONFIDENCE_CHECK)
                : null;
        }

        $gaps = [];
        for ($i = 1; $i < $count; $i++) {
            $gaps[] = (int) $transactions[$i - 1]->date->diffInDays($transactions[$i]->date);
        }
        sort($gaps);
        $mid = intdiv(count($gaps), 2);
        $median = count($gaps) % 2 ? $gaps[$mid] : intdiv($gaps[$mid - 1] + $gaps[$mid], 2);

        foreach (self::RHYTHMS as [$min, $max, $unit, $intervalCount]) {
            if ($median >= $min && $median <= $max) {
                // Without a mandate every gap must fit (filters irregular payments like groceries).
                if (! $isMandate && ($gaps[0] < $min - self::GAP_SLACK || $gaps[count($gaps) - 1] > $max + self::GAP_SLACK)) {
                    return null;
                }

                return new DetectedPayment(
                    $payee,
                    $transactions,
                    $unit,
                    $intervalCount,
                    $count >= 3 ? DetectedPayment::CONFIDENCE_HIGH : DetectedPayment::CONFIDENCE_CHECK,
                );
            }
        }

        // A mandate with an unusual rhythm is still a contract – let the user decide.
        return $isMandate
            ? new DetectedPayment($payee, $transactions, null, null, DetectedPayment::CONFIDENCE_CHECK)
            : null;
    }

    /**
     * Same payee, different amounts (e.g. two Amazon contracts): cluster by ±10 %.
     *
     * @param  list<Transaction>  $transactions
     * @return list<list<Transaction>>
     */
    private function splitByAmount(array $transactions): array
    {
        usort($transactions, fn (Transaction $a, Transaction $b) => $a->amountCents <=> $b->amountCents);

        $clusters = [];
        foreach ($transactions as $t) {
            $last = count($clusters) - 1;
            if ($last >= 0) {
                $ref = $clusters[$last][0]->amountCents;
                if (abs($t->amountCents - $ref) <= $ref * self::AMOUNT_TOLERANCE) {
                    $clusters[$last][] = $t;

                    continue;
                }
            }
            $clusters[] = [$t];
        }

        return $clusters;
    }
}
