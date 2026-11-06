<?php

use App\Models\PriceChange;
use App\Models\Subscription;
use App\Models\User;
use Carbon\CarbonImmutable;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    CarbonImmutable::setTestNow('2026-11-06 10:00:00');
    $this->user = User::factory()->create();
    Sanctum::actingAs($this->user);
    $this->make = fn (array $attrs) => Subscription::factory()->for($this->user)->create($attrs);

    ($this->make)(['name' => 'Internet', 'amount_cents' => 3999, 'anchor_date' => '2026-01-01']);
    $this->netflix = ($this->make)(['name' => 'Netflix', 'amount_cents' => 1399, 'anchor_date' => '2026-01-15']);
    ($this->make)([
        'name' => 'Adobe CC', 'amount_cents' => 14388, 'interval_unit' => 'year', 'anchor_date' => '2026-11-12',
        'start_date' => '2023-11-12', 'min_term_months' => 12, 'renewal_months' => 12, 'notice_value' => 2, 'notice_unit' => 'day',
    ]);
    $this->strava = ($this->make)(['name' => 'Strava', 'amount_cents' => 5999, 'interval_unit' => 'year', 'anchor_date' => '2026-11-21']);
    ($this->make)(['name' => 'ADAC', 'amount_cents' => 13900, 'interval_unit' => 'year', 'anchor_date' => '2027-03-01']);
    ($this->make)(['name' => 'Hausrat', 'amount_cents' => 8940, 'interval_unit' => 'year', 'anchor_date' => '2027-04-01']);
    // charges until its end, then not any more
    ($this->make)(['name' => 'Zeitung', 'amount_cents' => 2000, 'anchor_date' => '2026-01-20', 'status' => 'cancelled', 'ends_at' => '2026-11-30']);
    // never charges
    ($this->make)(['name' => 'Pausiert', 'amount_cents' => 999, 'anchor_date' => '2026-01-10', 'status' => 'paused']);
});

afterEach(function () {
    CarbonImmutable::setTestNow();
});

describe('calendar', function () {
    it('lists the charges per day with total and open amount', function () {
        $data = $this->getJson('/api/v1/calendar?month=2026-11')->assertOk()->json('data');

        expect($data['month'])->toBe('2026-11')
            ->and($data['first_day'])->toBe('2026-11-01')
            ->and($data['days_in_month'])->toBe(30)
            ->and($data['today'])->toBe('2026-11-06')
            ->and(array_column($data['days'], 'date'))->toBe(['2026-11-01', '2026-11-12', '2026-11-15', '2026-11-20', '2026-11-21'])
            ->and(array_column($data['days'], 'total_cents'))->toBe([3999, 14388, 1399, 2000, 5999])
            ->and(array_column($data['days'], 'level'))->toBe([2, 3, 1, 1, 2])
            ->and($data['total_cents'])->toBe(27785)
            // from today (6.11.) on
            ->and($data['open_cents'])->toBe(23786);
    });

    it('shows the deadline only for subscriptions with a term', function () {
        $days = collect($this->getJson('/api/v1/calendar?month=2026-11')->json('data.days'))->keyBy('date');

        expect($days['2026-11-12']['items'][0])->toMatchArray([
            'name' => 'Adobe CC', 'amount_cents' => 14388, 'rhythm_label' => 'jährlich', 'cancel_by_date' => '2026-11-10',
        ])
            ->and($days['2026-11-21']['items'][0]['cancel_by_date'])->toBeNull();
    });

    it('stops charging cancelled subscriptions after their end', function () {
        $data = $this->getJson('/api/v1/calendar?month=2026-12')->assertOk()->json('data');

        expect(array_column($data['days'], 'date'))->toBe(['2026-12-01', '2026-12-15'])
            ->and($data['total_cents'])->toBe(5398);
    });

    it('defaults to the current month and validates the format', function () {
        expect($this->getJson('/api/v1/calendar')->assertOk()->json('data.month'))->toBe('2026-11');

        $this->getJson('/api/v1/calendar?month=2026-13')->assertUnprocessable()->assertJsonValidationErrors('month');
        $this->getJson('/api/v1/calendar?month=11.2026')->assertUnprocessable();
    });

    it('uses the announced price from its effective date', function () {
        PriceChange::query()->create([
            'subscription_id' => $this->netflix->id, 'old_cents' => 1399, 'new_cents' => 1599,
            'effective_date' => '2026-12-01', 'source' => 'manual', 'status' => 'accepted',
        ]);

        $nov = collect($this->getJson('/api/v1/calendar?month=2026-11')->json('data.days'))->keyBy('date');
        $dec = collect($this->getJson('/api/v1/calendar?month=2026-12')->json('data.days'))->keyBy('date');

        expect($nov['2026-11-15']['total_cents'])->toBe(1399)
            ->and($dec['2026-12-15']['total_cents'])->toBe(1599);
    });
});

describe('reserve', function () {
    it('computes the monthly reserve for long intervals', function () {
        $data = $this->getJson('/api/v1/stats/reserve')->assertOk()->json('data');

        // 14388 + 5999 + 13900 + 8940 = 43227 per year → 3602.25 per month
        expect($data['yearly_cents'])->toBe(43227)
            ->and($data['monthly_cents'])->toBe(3602)
            ->and($data['subscription_count'])->toBe(4);
    });

    it('simulates the next 12 months and recommends a buffer', function () {
        $data = $this->getJson('/api/v1/stats/reserve')->json('data');

        expect(array_column($data['months'], 'month'))->toBe([
            '2026-12', '2027-01', '2027-02', '2027-03', '2027-04', '2027-05',
            '2027-06', '2027-07', '2027-08', '2027-09', '2027-10', '2027-11',
        ])
            ->and(array_column($data['months'], 'payments_cents'))->toBe([0, 0, 0, 13900, 8940, 0, 0, 0, 0, 0, 0, 20387])
            // exact running balance, rounded per month: April is −4828.75
            ->and(array_column($data['months'], 'balance_cents'))->toBe([
                3602, 7205, 10807, 509, -4829, -1227, 2376, 5978, 9580, 13183, 16785, 0,
            ])
            // lowest −48,29 € → rounded up to 50 €
            ->and($data['buffer_cents'])->toBe(5000)
            ->and(array_column($data['months'][10 + 1]['payments'], 'name'))->toEqualCanonicalizing(['Adobe CC', 'Strava']);
    });

    it('lists the next yearly payments from today', function () {
        $upcoming = $this->getJson('/api/v1/stats/reserve')->json('data.upcoming');

        expect(array_column($upcoming, 'name'))->toBe(['Adobe CC', 'Strava', 'ADAC', 'Hausrat'])
            ->and(array_column($upcoming, 'date'))->toBe(['2026-11-12', '2026-11-21', '2027-03-01', '2027-04-01']);
    });

    it('needs no buffer when the reserve always covers the charges', function () {
        Subscription::query()->where('interval_unit', 'year')->delete();
        ($this->make)(['name' => 'Spät', 'amount_cents' => 1200, 'interval_unit' => 'year', 'anchor_date' => '2027-11-01']);

        $data = $this->getJson('/api/v1/stats/reserve')->json('data');

        expect($data['monthly_cents'])->toBe(100)
            ->and($data['buffer_cents'])->toBe(0)
            ->and(end($data['months'])['balance_cents'])->toBe(0);
    });

    it('uses future accepted prices in the simulation', function () {
        PriceChange::query()->create([
            'subscription_id' => $this->strava->id, 'old_cents' => 5999, 'new_cents' => 6999,
            'effective_date' => '2027-06-01', 'source' => 'manual', 'status' => 'accepted',
        ]);

        $months = $this->getJson('/api/v1/stats/reserve')->json('data.months');

        expect(end($months)['payments_cents'])->toBe(14388 + 6999);
    });
});

describe('history', function () {
    it('ends with the overview total of the current month', function () {
        $data = $this->getJson('/api/v1/stats/history?months=12')->assertOk()->json('data');
        $dashboard = $this->getJson('/api/v1/dashboard')->json('data');

        expect($data['months'])->toHaveCount(12)
            ->and($data['months'][0]['month'])->toBe('2025-12')
            ->and(end($data['months']))->toBe(['month' => '2026-11', 'total_cents' => $dashboard['total_cents']])
            ->and($data['current_cents'])->toBe(9000);
    });

    it('counts cancelled subscriptions until their end', function () {
        $data = $this->getJson('/api/v1/stats/history')->json('data');
        $byMonth = collect($data['months'])->pluck('total_cents', 'month');

        // October still with the newspaper (ends 30.11.), November only active ones
        expect($byMonth['2026-10'])->toBe(11000)
            ->and($data['change_cents'])->toBe(-2000);
    });

    it('starts subscriptions at their start date and follows applied price changes', function () {
        ($this->make)(['name' => 'Disney+', 'amount_cents' => 999, 'anchor_date' => '2026-06-15', 'start_date' => '2026-06-15']);
        PriceChange::query()->create([
            'subscription_id' => $this->netflix->id, 'old_cents' => 1299, 'new_cents' => 1399,
            'effective_date' => '2026-09-01', 'source' => 'import', 'status' => 'accepted',
        ])->forceFill(['applied_at' => '2026-09-01 00:05:00'])->save();

        $byMonth = collect($this->getJson('/api/v1/stats/history')->json('data.months'))->pluck('total_cents', 'month');

        // May: no Disney+, Netflix at the old price
        expect($byMonth['2026-05'])->toBe(11000 - 100)
            ->and($byMonth['2026-06'])->toBe(11000 - 100 + 999)
            ->and($byMonth['2026-09'])->toBe(11000 + 999);
    });

    it('validates the number of months', function () {
        $this->getJson('/api/v1/stats/history?months=1')->assertUnprocessable();
        $this->getJson('/api/v1/stats/history?months=99')->assertUnprocessable();
    });
});

it('shows the open deadlines of the next 30 days on the dashboard', function () {
    expect($this->getJson('/api/v1/dashboard')->json('data.deadlines'))->toBe([
        'count' => 1,
        'next' => ['subscription_id' => Subscription::query()->where('name', 'Adobe CC')->value('id'), 'name' => 'Adobe CC', 'date' => '2026-11-10'],
    ]);
});

it('never mixes in data of other users', function () {
    $bob = User::factory()->create();
    Subscription::factory()->for($bob)->create(['name' => 'Bob', 'amount_cents' => 50000, 'anchor_date' => '2026-01-03']);
    Subscription::factory()->for($bob)->yearly(70000)->create(['name' => 'Bob Jahr', 'anchor_date' => '2027-01-03']);

    $calendar = $this->getJson('/api/v1/calendar?month=2026-11')->json('data');
    expect($calendar['total_cents'])->toBe(27785)
        ->and(collect($calendar['days'])->pluck('items')->flatten(1)->pluck('name'))->not->toContain('Bob');
    expect($this->getJson('/api/v1/stats/reserve')->json('data.yearly_cents'))->toBe(43227);
    expect($this->getJson('/api/v1/stats/history')->json('data.current_cents'))->toBe(9000);

    Sanctum::actingAs($bob);
    expect($this->getJson('/api/v1/calendar?month=2026-11')->json('data.total_cents'))->toBe(50000)
        ->and($this->getJson('/api/v1/stats/reserve')->json('data.yearly_cents'))->toBe(70000)
        ->and($this->getJson('/api/v1/stats/history')->json('data.current_cents'))->toBe(50000 + 5833);
});

it('requires login', function () {
    auth()->forgetGuards();

    $this->getJson('/api/v1/calendar')->assertUnauthorized();
    $this->getJson('/api/v1/stats/reserve')->assertUnauthorized();
    $this->getJson('/api/v1/stats/history')->assertUnauthorized();
});
