<?php

use App\Models\ReminderSetting;
use App\Models\Subscription;
use App\Services\Reminders\DueReminder;
use App\Services\Reminders\ReminderPlanner;
use Carbon\CarbonImmutable;

function onDay(string $date): CarbonImmutable
{
    return CarbonImmutable::parse($date, 'Europe/Berlin')->startOfDay();
}

/** Adobe-like contract: cancel by 10.11.2026 (term end 12.11.2026, notice 2 days). */
function termSub(array $overrides = []): Subscription
{
    $s = new Subscription(array_merge([
        'name' => 'Adobe Creative Cloud',
        'amount_cents' => 14388,
        'interval_unit' => 'year',
        'interval_count' => 1,
        'anchor_date' => '2026-11-12',
        'start_date' => '2023-11-12',
        'min_term_months' => 12,
        'renewal_months' => 12,
        'notice_value' => 2,
        'notice_unit' => 'day',
        'status' => 'active',
        'remind' => true,
    ], $overrides));
    $s->id = $overrides['id'] ?? 1;

    return $s;
}

function reminderSettings(array $offsets = [14, 3], bool $charges = false): ReminderSetting
{
    return new ReminderSetting(['offsets_days' => $offsets, 'push' => true, 'email' => true, 'charge_reminders' => $charges]);
}

function dueSummary(array $due): array
{
    return array_map(fn (DueReminder $r) => [$r->type, $r->dueDate->toDateString(), $r->offsetDays, $r->daysLeft], $due);
}

beforeEach(function () {
    $this->planner = app(ReminderPlanner::class);
});

it('reminds exactly on the offset days', function () {
    expect(dueSummary($this->planner->plan([termSub()], reminderSettings(), onDay('2026-10-27'))))->toBe([['deadline', '2026-11-10', 14, 14]])
        ->and(dueSummary($this->planner->plan([termSub()], reminderSettings(), onDay('2026-11-07'))))->toBe([['deadline', '2026-11-10', 3, 3]])
        ->and($this->planner->plan([termSub()], reminderSettings(), onDay('2026-11-05')))->toBe([]);
});

it('catches up reminders of the last two days', function () {
    // 3-day reminder was due on 07.11.; server was down on 07. and 08.
    expect(dueSummary($this->planner->plan([termSub()], reminderSettings(), onDay('2026-11-09'))))->toBe([['deadline', '2026-11-10', 3, 1]])
        // three days late is too late for the 14-day reminder (due 27.10.)
        ->and($this->planner->plan([termSub()], reminderSettings([14]), onDay('2026-10-30')))->toBe([]);
});

it('sends only the most urgent offset when several match', function () {
    $due = $this->planner->plan([termSub()], reminderSettings([7, 3, 1]), onDay('2026-11-09'));

    expect(dueSummary($due))->toBe([['deadline', '2026-11-10', 1, 1]])
        ->and($due[0]->alsoCovers)->toBe([3]);
});

it('does not remind for passed deadlines, cancel-anytime, disabled or inactive subscriptions', function () {
    $planner = $this->planner;

    // deadline passed: next deadline (10.11.2027) is far away
    expect($planner->plan([termSub()], reminderSettings([1]), onDay('2026-11-11')))->toBe([])
        ->and($planner->plan([termSub(['min_term_months' => null, 'start_date' => null])], reminderSettings(), onDay('2026-11-07')))->toBe([])
        ->and($planner->plan([termSub(['remind' => false])], reminderSettings(), onDay('2026-11-07')))->toBe([])
        ->and($planner->plan([termSub(['status' => 'paused'])], reminderSettings(), onDay('2026-11-07')))->toBe([])
        ->and($planner->plan([termSub(['status' => 'cancelled', 'ends_at' => '2026-11-11'])], reminderSettings(), onDay('2026-11-07')))->toBe([]);
});

it('reminds of tomorrow\'s charge only for long intervals and only when enabled', function () {
    $yearly = termSub(['id' => 2, 'name' => 'Strava', 'min_term_months' => null, 'start_date' => null, 'anchor_date' => '2026-11-21']);
    $monthly = termSub(['id' => 3, 'name' => 'Netflix', 'interval_unit' => 'month', 'min_term_months' => null, 'start_date' => null, 'anchor_date' => '2026-10-21']);

    expect(dueSummary($this->planner->plan([$yearly, $monthly], reminderSettings([14], charges: true), onDay('2026-11-20'))))
        ->toBe([['charge', '2026-11-21', 1, 1]])
        ->and($this->planner->plan([$yearly], reminderSettings([14], charges: false), onDay('2026-11-20')))->toBe([])
        ->and($this->planner->plan([$yearly], reminderSettings([14], charges: true), onDay('2026-11-19')))->toBe([]);
});

it('handles deadlines at month ends and across DST', function () {
    // term end 31.03.2027 (from 31.03.2026, 12 months), notice 1 month → cancel by 28.02.2027
    $sub = termSub(['start_date' => '2026-03-31', 'renewal_months' => 1, 'notice_value' => 1, 'notice_unit' => 'month', 'anchor_date' => '2026-03-31']);

    expect(dueSummary($this->planner->plan([$sub], reminderSettings([3]), onDay('2027-02-25'))))->toBe([['deadline', '2027-02-28', 3, 3]]);

    // cancel by 01.04.2026 → 3-day reminder on 29.03.2026 (DST switch day)
    $dst = termSub(['start_date' => '2025-04-02', 'renewal_months' => 12, 'notice_value' => 1, 'notice_unit' => 'day', 'anchor_date' => '2025-04-02']);
    expect(dueSummary($this->planner->plan([$dst], reminderSettings([3]), onDay('2026-03-29'))))->toBe([['deadline', '2026-04-01', 3, 3]]);
});
