<?php

use App\Enums\IntervalUnit;
use App\Models\Subscription;
use App\Services\BillingCalculator;
use Carbon\CarbonImmutable;

function d(string $date): CarbonImmutable
{
    return CarbonImmutable::parse($date, 'Europe/Berlin')->startOfDay();
}

beforeEach(function () {
    $this->calc = new BillingCalculator;
});

describe('monthly conversion', function () {
    it('converts rhythms to monthly cents', function (int $amount, IntervalUnit $unit, int $count, int $expected) {
        expect($this->calc->monthly($amount, $unit, $count)->round())->toBe($expected);
    })->with([
        'monthly' => [1399, IntervalUnit::Month, 1, 1399],
        'yearly 143,88 €' => [14388, IntervalUnit::Year, 1, 1199],
        'quarterly 30 €' => [3000, IntervalUnit::Month, 3, 1000],
        'half-yearly 60 €' => [6000, IntervalUnit::Month, 6, 1000],
        'weekly 10 €' => [1000, IntervalUnit::Week, 1, 4333],
        'every 2 weeks 10 €' => [1000, IntervalUnit::Week, 2, 2167],
        'every 2 years 240 €' => [24000, IntervalUnit::Year, 2, 1000],
    ]);

    it('computes yearly and daily values', function () {
        expect($this->calc->yearly(14388, IntervalUnit::Year)->round())->toBe(14388)
            ->and($this->calc->yearly(1399, IntervalUnit::Month)->round())->toBe(16788)
            ->and($this->calc->daily(14388, IntervalUnit::Year)->round())->toBe(39);
    });

    it('rejects an interval count below 1', function () {
        $this->calc->monthly(100, IntervalUnit::Month, 0);
    })->throws(InvalidArgumentException::class);
});

describe('next charge', function () {
    it('returns the anchor when it is today or in the future', function () {
        expect($this->calc->nextCharge(d('2026-11-20'), IntervalUnit::Month, 1, d('2026-11-06'))->toDateString())
            ->toBe('2026-11-20')
            ->and($this->calc->nextCharge(d('2026-11-06'), IntervalUnit::Month, 1, d('2026-11-06'))->toDateString())
            ->toBe('2026-11-06');
    });

    it('keeps the 31st: February gets the last day, March the 31st again', function () {
        $anchor = d('2026-01-31');

        expect($this->calc->nextCharge($anchor, IntervalUnit::Month, 1, d('2026-02-01'))->toDateString())->toBe('2026-02-28')
            ->and($this->calc->nextCharge($anchor, IntervalUnit::Month, 1, d('2026-03-01'))->toDateString())->toBe('2026-03-31')
            ->and($this->calc->nextCharge($anchor, IntervalUnit::Month, 1, d('2026-04-01'))->toDateString())->toBe('2026-04-30')
            ->and($this->calc->nextCharge($anchor, IntervalUnit::Month, 1, d('2026-05-01'))->toDateString())->toBe('2026-05-31');
    });

    it('handles leap years', function () {
        expect($this->calc->nextCharge(d('2027-01-31'), IntervalUnit::Month, 1, d('2028-02-01'))->toDateString())
            ->toBe('2028-02-29')
            // yearly subscription started on Feb 29
            ->and($this->calc->nextCharge(d('2024-02-29'), IntervalUnit::Year, 1, d('2026-01-01'))->toDateString())
            ->toBe('2026-02-28')
            ->and($this->calc->nextCharge(d('2024-02-29'), IntervalUnit::Year, 1, d('2028-01-01'))->toDateString())
            ->toBe('2028-02-29');
    });

    it('does not drift when computed many months later', function () {
        // Month-by-month adding would end on the 28th after February; from the anchor it stays at the 31st.
        expect($this->calc->nextCharge(d('2020-01-31'), IntervalUnit::Month, 1, d('2026-12-01'))->toDateString())
            ->toBe('2026-12-31');
    });

    it('handles quarterly and multi-month intervals', function () {
        expect($this->calc->nextCharge(d('2026-01-15'), IntervalUnit::Month, 3, d('2026-04-16'))->toDateString())
            ->toBe('2026-07-15')
            ->and($this->calc->nextCharge(d('2025-11-30'), IntervalUnit::Month, 3, d('2026-01-01'))->toDateString())
            ->toBe('2026-02-28');
    });

    it('handles weekly charges across the DST switch', function () {
        // 2026-03-29: clocks go forward in Germany
        expect($this->calc->nextCharge(d('2026-03-23'), IntervalUnit::Week, 1, d('2026-03-24'))->toDateString())
            ->toBe('2026-03-30')
            ->and($this->calc->nextCharge(d('2026-10-19'), IntervalUnit::Week, 2, d('2026-10-20'))->toDateString())
            ->toBe('2026-11-02');
    });

    it('returns null after the end date', function () {
        expect($this->calc->nextCharge(d('2026-01-10'), IntervalUnit::Month, 1, d('2026-11-11'), d('2026-12-31')))
            ->not->toBeNull()
            ->and($this->calc->nextCharge(d('2026-01-10'), IntervalUnit::Month, 1, d('2026-11-11'), d('2026-12-09')))
            ->toBeNull();
    });
});

describe('charges between', function () {
    it('lists every charge in a range', function () {
        $sub = new Subscription([
            'amount_cents' => 1000, 'interval_unit' => 'month', 'interval_count' => 1, 'anchor_date' => '2026-01-31',
        ]);

        $dates = array_map(fn ($d) => $d->toDateString(), $this->calc->chargesBetween($sub, d('2026-02-01'), d('2026-05-31')));

        expect($dates)->toBe(['2026-02-28', '2026-03-31', '2026-04-30', '2026-05-31']);
    });
});

it('labels rhythms in German', function (IntervalUnit $unit, int $count, string $label) {
    expect($this->calc->rhythmLabel($unit, $count))->toBe($label);
})->with([
    [IntervalUnit::Week, 1, 'wöchentlich'],
    [IntervalUnit::Week, 2, 'alle 2 Wochen'],
    [IntervalUnit::Month, 1, 'monatlich'],
    [IntervalUnit::Month, 3, 'vierteljährlich'],
    [IntervalUnit::Month, 6, 'halbjährlich'],
    [IntervalUnit::Month, 2, 'alle 2 Monate'],
    [IntervalUnit::Year, 1, 'jährlich'],
    [IntervalUnit::Year, 2, 'alle 2 Jahre'],
]);
