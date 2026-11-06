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
    // Adobe CC: start 12.11.2023, 12 months, renews 12 months, notice 1 day
    // → renews 12.11.2026, contract ends 11.11., cancellation must arrive by 10.11.
    $result = $this->calc->compute(day('2023-11-12'), 12, 12, 1, NoticeUnit::Day, day('2026-11-05'));

    expect(dates($result))->toBe(['2026-11-12', '2026-11-10']);
});

it('moves to the next term once the deadline has passed', function () {
    // Deadline 10.11.2026 missed → next possible: term end 12.11.2027, cancel by 10.11.2027
    $result = $this->calc->compute(day('2023-11-12'), 12, 12, 1, NoticeUnit::Day, day('2026-11-11'));

    expect(dates($result))->toBe(['2027-11-12', '2027-11-10']);
});

it('keeps the deadline on its last day', function () {
    expect(dates($this->calc->compute(day('2023-11-12'), 12, 12, 1, NoticeUnit::Day, day('2026-11-10'))))
        ->toBe(['2026-11-12', '2026-11-10']);
});

it('renews monthly after the minimum term if no renewal is given', function () {
    // Gym: start 01.03.2026, 24 months min term, 1 month notice
    expect(dates($this->calc->compute(day('2026-03-01'), 24, null, 1, NoticeUnit::Month, day('2026-11-06'))))
        ->toBe(['2028-03-01', '2028-01-31'])
        ->and(dates($this->calc->compute(day('2024-03-01'), 24, null, 1, NoticeUnit::Month, day('2026-11-06'))))
        // term ended 01.03.2026, monthly since; deadline for renewal 01.12. (31.10.) is gone → 01.01.2027 / 30.11.2026
        ->toBe(['2027-01-01', '2026-11-30']);
});

it('handles month-end start dates without overflow', function () {
    // start 31.01.2026, 1 month term → renews 28.02.2026; notice 14 days → 13.02.2026
    expect(dates($this->calc->compute(day('2026-01-31'), 1, 1, 14, NoticeUnit::Day, day('2026-02-01'))))
        ->toBe(['2026-02-28', '2026-02-13'])
        // later renewals from the start date: 31.03., not 28.03.
        ->and(dates($this->calc->compute(day('2026-01-31'), 1, 1, 14, NoticeUnit::Day, day('2026-03-01'))))
        ->toBe(['2026-03-31', '2026-03-16']);
});

it('leaves the full notice period before the contract ends', function () {
    // Telekom: start 01.12.2024, 24 months, then monthly, notice 1 month.
    // Renewal 01.12.2026 (contract ends 30.11.) needed notice by 31.10. → gone.
    // Next: renewal 01.01.2027, contract ends 31.12.2026 → cancel by 30.11.2026.
    expect(dates($this->calc->compute(day('2024-12-01'), 24, 1, 1, NoticeUnit::Month, day('2026-11-06'))))
        ->toBe(['2027-01-01', '2026-11-30'])
        // 14 days notice to a contract ending 09.04.: received 26.03. leaves 27.03.–09.04. = 14 days
        ->and(dates($this->calc->compute(day('2026-04-10'), 12, 12, 14, NoticeUnit::Day, day('2026-11-06'))))
        ->toBe(['2027-04-10', '2027-03-26']);
});

it('requires cancelling on the last contract day at the latest without a notice period', function () {
    expect(dates($this->calc->compute(day('2026-01-15'), 12, 12, null, null, day('2026-11-06'))))
        ->toBe(['2027-01-15', '2027-01-14']);
});

it('subtracts notice in weeks and months without overflow', function () {
    expect($this->calc->subtractNotice(day('2026-03-31'), 1, NoticeUnit::Month)->toDateString())->toBe('2026-02-28')
        ->and($this->calc->subtractNotice(day('2028-03-31'), 1, NoticeUnit::Month)->toDateString())->toBe('2028-02-29')
        ->and($this->calc->subtractNotice(day('2026-11-12'), 2, NoticeUnit::Week)->toDateString())->toBe('2026-10-29')
        ->and($this->calc->subtractNotice(day('2026-11-12'), null, null)->toDateString())->toBe('2026-11-12');
});

it('stays correct across the DST switch', function () {
    // renews 01.04.2026, notice 7 days → 24.03.2026 (DST on 29.03.)
    expect(dates($this->calc->compute(day('2025-04-01'), 12, 12, 7, NoticeUnit::Day, day('2026-03-20'))))
        ->toBe(['2026-04-01', '2026-03-24']);
});

it('is fast for very old contracts', function () {
    $result = $this->calc->compute(day('1990-01-15'), 12, 1, 1, NoticeUnit::Month, day('2026-11-06'));

    expect(dates($result))->toBe(['2026-12-15', '2026-11-14']);
});

it('labels notice periods in German', function () {
    expect($this->calc->noticeLabel(14, NoticeUnit::Day))->toBe('14 Tage')
        ->and($this->calc->noticeLabel(1, NoticeUnit::Month))->toBe('1 Monat')
        ->and($this->calc->noticeLabel(3, NoticeUnit::Month))->toBe('3 Monate')
        ->and($this->calc->noticeLabel(null, null))->toBeNull();
});
