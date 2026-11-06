<?php

use App\Enums\NoticeUnit;
use App\Services\DeadlineCalculator;
use Carbon\CarbonImmutable;

function day(string $date): CarbonImmutable
{
    return CarbonImmutable::parse($date, 'Europe/Berlin')->startOfDay();
}

beforeEach(function () {
    $this->calc = new DeadlineCalculator;
});

function dates(array $result): array
{
    return [$result['term_end']?->toDateString(), $result['cancel_by']?->toDateString()];
}

it('has no deadline without a minimum term (cancel anytime)', function () {
    expect(dates($this->calc->compute(day('2026-01-01'), null, null, 1, NoticeUnit::Month, day('2026-11-06'))))
        ->toBe([null, null])
        ->and(dates($this->calc->compute(null, 12, 12, 1, NoticeUnit::Month, day('2026-11-06'))))
        ->toBe([null, null]);
});

it('computes the design example: yearly renewal, cancel by 10.11.', function () {
    // Adobe CC: start 12.11.2023, 12 months, renews 12 months, notice 2 days → term end 12.11.2026
    $result = $this->calc->compute(day('2023-11-12'), 12, 12, 2, NoticeUnit::Day, day('2026-11-05'));

    expect(dates($result))->toBe(['2026-11-12', '2026-11-10']);
});

it('moves to the next term once the deadline has passed', function () {
    // Deadline 10.11.2026 missed → next possible: term end 12.11.2027, cancel by 10.11.2027
    $result = $this->calc->compute(day('2023-11-12'), 12, 12, 2, NoticeUnit::Day, day('2026-11-11'));

    expect(dates($result))->toBe(['2027-11-12', '2027-11-10']);
});

it('keeps the deadline on its last day', function () {
    expect(dates($this->calc->compute(day('2023-11-12'), 12, 12, 2, NoticeUnit::Day, day('2026-11-10'))))
        ->toBe(['2026-11-12', '2026-11-10']);
});

it('renews monthly after the minimum term if no renewal is given', function () {
    // Gym: start 01.03.2026, 24 months min term, 1 month notice
    expect(dates($this->calc->compute(day('2026-03-01'), 24, null, 1, NoticeUnit::Month, day('2026-11-06'))))
        ->toBe(['2028-03-01', '2028-02-01'])
        ->and(dates($this->calc->compute(day('2024-03-01'), 24, null, 1, NoticeUnit::Month, day('2026-11-06'))))
        // term ended 01.03.2026, monthly since; deadline for 01.12. (01.11.) is gone → 01.01.2027 / 01.12.2026
        ->toBe(['2027-01-01', '2026-12-01']);
});

it('handles month-end start dates without overflow', function () {
    // start 31.01.2026, 1 month term → 28.02.2026; notice 14 days → 14.02.2026
    expect(dates($this->calc->compute(day('2026-01-31'), 1, 1, 14, NoticeUnit::Day, day('2026-02-01'))))
        ->toBe(['2026-02-28', '2026-02-14'])
        // later renewals from the start date: 31.03., not 28.03.
        ->and(dates($this->calc->compute(day('2026-01-31'), 1, 1, 14, NoticeUnit::Day, day('2026-03-01'))))
        ->toBe(['2026-03-31', '2026-03-17']);
});

it('subtracts notice in weeks and months without overflow', function () {
    expect($this->calc->subtractNotice(day('2026-03-31'), 1, NoticeUnit::Month)->toDateString())->toBe('2026-02-28')
        ->and($this->calc->subtractNotice(day('2028-03-31'), 1, NoticeUnit::Month)->toDateString())->toBe('2028-02-29')
        ->and($this->calc->subtractNotice(day('2026-11-12'), 2, NoticeUnit::Week)->toDateString())->toBe('2026-10-29')
        ->and($this->calc->subtractNotice(day('2026-11-12'), null, null)->toDateString())->toBe('2026-11-12');
});

it('stays correct across the DST switch', function () {
    // term end 01.04.2026, notice 7 days → 25.03.2026 (DST on 29.03.)
    expect(dates($this->calc->compute(day('2025-04-01'), 12, 12, 7, NoticeUnit::Day, day('2026-03-20'))))
        ->toBe(['2026-04-01', '2026-03-25']);
});

it('is fast for very old contracts', function () {
    $result = $this->calc->compute(day('1990-01-15'), 12, 1, 1, NoticeUnit::Month, day('2026-11-06'));

    expect(dates($result))->toBe(['2026-12-15', '2026-11-15']);
});

it('labels notice periods in German', function () {
    expect($this->calc->noticeLabel(14, NoticeUnit::Day))->toBe('14 Tage')
        ->and($this->calc->noticeLabel(1, NoticeUnit::Month))->toBe('1 Monat')
        ->and($this->calc->noticeLabel(3, NoticeUnit::Month))->toBe('3 Monate')
        ->and($this->calc->noticeLabel(null, null))->toBeNull();
});
