<?php

use App\Models\Category;
use App\Models\Subscription;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\CategorySeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    CarbonImmutable::setTestNow('2026-11-05 10:00:00');
    $this->seed(CategorySeeder::class);
    $this->user = User::factory()->create();
    Sanctum::actingAs($this->user);
    $cat = fn (string $key) => Category::query()->where('key', $key)->value('id');

    $this->make = fn (array $attrs) => Subscription::factory()->for($this->user)->create($attrs);

    ($this->make)(['name' => 'Internet', 'amount_cents' => 3999, 'anchor_date' => '2026-12-01', 'category_id' => $cat('telecom')]);
    ($this->make)(['name' => 'Netflix', 'amount_cents' => 1399, 'anchor_date' => '2026-10-15', 'category_id' => $cat('streaming')]);
    ($this->make)(['name' => 'Strava', 'amount_cents' => 5999, 'interval_unit' => 'year', 'anchor_date' => '2026-11-21', 'category_id' => $cat('sport')]);
    ($this->make)([
        'name' => 'Adobe Creative Cloud', 'amount_cents' => 14388, 'interval_unit' => 'year', 'anchor_date' => '2026-11-12',
        'start_date' => '2023-11-12', 'min_term_months' => 12, 'renewal_months' => 12, 'notice_value' => 2, 'notice_unit' => 'day',
        'category_id' => $cat('software'),
    ]);
    ($this->make)(['name' => 'Ohne Kategorie', 'amount_cents' => 100, 'interval_unit' => 'year', 'anchor_date' => '2027-03-01']);
    // not counted
    ($this->make)(['name' => 'Gekündigt', 'amount_cents' => 9999, 'status' => 'cancelled', 'ends_at' => '2026-11-30']);
    ($this->make)(['name' => 'Pausiert', 'amount_cents' => 9999, 'status' => 'paused']);
});

afterEach(function () {
    CarbonImmutable::setTestNow();
});

it('sums only active subscriptions, exactly and rounded once', function () {
    // 3999 + 1399 + 5999/12 + 14388/12 + 100/12 = 5398 + 20487/12 = 5398 + 1707.25 = 7105.25 → 7105
    $data = $this->getJson('/api/v1/dashboard')->assertOk()->json('data');

    expect($data['period'])->toBe('month')
        ->and($data['total_cents'])->toBe(7105)
        ->and($data['subscription_count'])->toBe(5)
        ->and($data['converted_count'])->toBe(3)
        ->and($data['inactive_count'])->toBe(2);
});

it('returns yearly totals from the exact sum', function () {
    // 7105.25 × 12 = 85263
    expect($this->getJson('/api/v1/dashboard?period=year')->json('data.total_cents'))->toBe(85263);
});

it('breaks totals down by category, biggest first', function () {
    $categories = $this->getJson('/api/v1/dashboard')->json('data.categories');

    expect(array_column($categories, 'label'))->toBe([
        'Telefon & Internet', 'Streaming', 'Software & Cloud', 'Sport', 'Ohne Kategorie',
    ])
        ->and($categories[0]['total_cents'])->toBe(3999)
        ->and($categories[0]['category']['color'])->toBe('#8EA8FF')
        ->and($categories[2]['total_cents'])->toBe(1199)
        ->and($categories[4]['category'])->toBeNull()
        ->and(round(array_sum(array_column($categories, 'share')), 2))->toBe(1.0);
});

it('lists deadlines first, then the next charges', function () {
    $upcoming = $this->getJson('/api/v1/dashboard')->json('data.upcoming');

    expect(array_map(fn ($i) => [$i['type'], $i['subscription']['name'], $i['date']], $upcoming))->toBe([
        ['deadline', 'Adobe Creative Cloud', '2026-11-10'],
        // Adobe's charge on 12.11. is not repeated; Strava (21.11.) is beyond the 14-day window.
        ['charge', 'Netflix', '2026-11-15'],
    ])->and($upcoming[0]['days'])->toBe(5)
        ->and($upcoming[0]['subscription']['term_end_date'])->toBe('2026-11-12');
});

it('shows the biggest items by monthly cost', function () {
    $biggest = $this->getJson('/api/v1/dashboard')->json('data.biggest');

    expect(array_map(fn ($i) => [$i['subscription']['name'], $i['period_cents']], array_slice($biggest, 0, 3)))->toBe([
        ['Internet', 3999],
        ['Netflix', 1399],
        ['Adobe Creative Cloud', 1199],
    ]);
});

it('returns an empty overview for a new user and never mixes tenants', function () {
    Sanctum::actingAs(User::factory()->create());

    $data = $this->getJson('/api/v1/dashboard')->assertOk()->json('data');

    expect($data['total_cents'])->toBe(0)
        ->and($data['subscription_count'])->toBe(0)
        ->and($data['categories'])->toBe([])
        ->and($data['upcoming'])->toBe([])
        ->and($data['biggest'])->toBe([]);
});
