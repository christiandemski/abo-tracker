<?php

use App\Models\Subscription;
use App\Models\UsageCheck;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\CategorySeeder;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    CarbonImmutable::setTestNow('2026-11-05 10:00:00');
    $this->seed(CategorySeeder::class);
    $this->user = User::factory()->create();
    Sanctum::actingAs($this->user);
});

afterEach(function () {
    CarbonImmutable::setTestNow();
});

describe('installments', function () {
    it('needs the last payment, not before the first one', function () {
        $base = ['kind' => 'installment', 'name' => 'Autokredit', 'amount_cents' => 25000, 'interval_unit' => 'month', 'anchor_date' => '2026-12-01'];

        $this->postJson('/api/v1/subscriptions', $base)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['ends_at' => 'Gib an, wann die letzte Rate fällig ist.']);

        $this->postJson('/api/v1/subscriptions', $base + ['ends_at' => '2026-11-30'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['ends_at' => 'Die letzte Rate kann nicht vor der ersten liegen.']);

        $this->postJson('/api/v1/subscriptions', $base + ['ends_at' => '2026-12-01'])->assertCreated();
    });

    it('counts the open installments month-end safe (31st, February)', function () {
        // Charges on the 31st: 30.11., 31.12., 31.01., 28.02.2027 (no overflow into March)
        $data = $this->postJson('/api/v1/subscriptions', [
            'kind' => 'installment', 'name' => 'Ratenkauf', 'amount_cents' => 4999, 'interval_unit' => 'month',
            'anchor_date' => '2026-01-31', 'ends_at' => '2027-02-28',
            // term fields make no sense for an installment and are dropped
            'start_date' => '2026-01-31', 'min_term_months' => 12, 'notice_value' => 1, 'notice_unit' => 'month',
        ])->assertCreated()->json('data');

        expect($data['kind'])->toBe('installment')
            ->and($data['kind_label'])->toBe('Rate')
            ->and($data['status'])->toBe('active')
            ->and($data['next_charge_date'])->toBe('2026-11-30')
            ->and($data['remaining_count'])->toBe(4)
            ->and($data['remaining_cents'])->toBe(4 * 4999)
            ->and($data['has_term'])->toBeFalse()
            ->and($data['min_term_months'])->toBeNull()
            ->and($data['notice_value'])->toBeNull()
            ->and($data['cancel_by_date'])->toBeNull();
    });

    it('ends on the 29th of February in a leap year', function () {
        $s = Subscription::factory()->for($this->user)->installment('2028-02-29')
            ->create(['amount_cents' => 1000, 'anchor_date' => '2026-01-31']);

        // 30.11.2026 … 29.02.2028 = 16 monthly payments
        expect($this->getJson("/api/v1/subscriptions/{$s->id}")->json('data.remaining_count'))->toBe(16);
    });

    it('takes announced price changes into account for the open amount', function () {
        $s = Subscription::factory()->for($this->user)->installment('2027-02-01')
            ->create(['amount_cents' => 10000, 'anchor_date' => '2026-12-01']);
        $this->postJson("/api/v1/subscriptions/{$s->id}/price-changes", ['new_cents' => 5000, 'effective_date' => '2027-02-01'])->assertCreated();

        // 01.12. + 01.01. at 100 €, last one on 01.02. at 50 €
        expect($this->getJson("/api/v1/subscriptions/{$s->id}")->json('data.remaining_cents'))->toBe(25000);
    });

    it('is finished after the last payment and reopens when it is moved', function () {
        $s = Subscription::factory()->for($this->user)->installment('2026-11-20')
            ->create(['name' => 'Handy-Rate', 'amount_cents' => 2000, 'anchor_date' => '2026-01-20']);
        expect($s->status->value)->toBe('active');

        CarbonImmutable::setTestNow('2026-11-21 03:00:00');
        Artisan::call('subscriptions:refresh-dates');
        $s->refresh();
        expect($s->status->value)->toBe('finished')
            ->and($s->next_charge_date)->toBeNull()
            ->and($this->getJson('/api/v1/dashboard')->json('data.total_cents'))->toBe(0)
            ->and($this->getJson('/api/v1/subscriptions?status=finished')->json('data.0.id'))->toBe($s->id);

        $this->patchJson("/api/v1/subscriptions/{$s->id}", ['ends_at' => '2027-01-20'])
            ->assertOk()
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.ends_at', '2027-01-20')
            ->assertJsonPath('data.remaining_count', 2); // 20.12. and 20.01.
    });

    it('keeps its last payment on edits and drops it when it becomes a subscription', function () {
        $s = Subscription::factory()->for($this->user)->installment('2027-06-01')
            ->create(['amount_cents' => 2000, 'anchor_date' => '2026-12-01']);

        $this->patchJson("/api/v1/subscriptions/{$s->id}", ['name' => 'Kredit'])->assertOk()->assertJsonPath('data.ends_at', '2027-06-01');
        $this->patchJson("/api/v1/subscriptions/{$s->id}", ['kind' => 'subscription'])
            ->assertOk()
            ->assertJsonPath('data.ends_at', null)
            ->assertJsonPath('data.remaining_count', null);
    });

    it('does not let the user set "finished" by hand', function () {
        $s = Subscription::factory()->for($this->user)->create();

        $this->patchJson("/api/v1/subscriptions/{$s->id}", ['status' => 'finished'])->assertJsonValidationErrors('status');
    });

    it('cannot be cancelled, reactivated or get a cancellation letter', function () {
        $s = Subscription::factory()->for($this->user)->installment('2027-06-01')->create();

        $this->postJson("/api/v1/subscriptions/{$s->id}/cancel")->assertUnprocessable();
        $this->postJson("/api/v1/subscriptions/{$s->id}/reactivate")->assertUnprocessable();
        $this->getJson("/api/v1/subscriptions/{$s->id}/cancellation-letter")->assertUnprocessable();
        expect($s->refresh()->ends_at->toDateString())->toBe('2027-06-01');
    });

    it('counts in the history up to its last payment', function () {
        Subscription::factory()->for($this->user)->installment('2026-09-15')
            ->create(['amount_cents' => 3000, 'anchor_date' => '2026-01-15', 'start_date' => '2026-01-15']);

        $months = collect($this->getJson('/api/v1/stats/history?months=4')->json('data.months'))->pluck('total_cents', 'month');

        // Month ends: 31.08. runs, 30.09. is after the last payment (15.09.)
        expect($months->all())->toBe(['2026-08' => 3000, '2026-09' => 0, '2026-10' => 0, '2026-11' => 0]);
    });
});

describe('savings', function () {
    beforeEach(function () {
        Subscription::factory()->for($this->user)->create(['name' => 'Netflix', 'amount_cents' => 1399, 'anchor_date' => '2026-11-15']);
        Subscription::factory()->for($this->user)->savings()->create([
            'name' => 'ETF-Sparplan', 'amount_cents' => 25000, 'anchor_date' => '2026-11-10',
            'min_term_months' => 12, 'start_date' => '2026-01-10',
        ]);
        Subscription::factory()->for($this->user)->savings()->create(['name' => 'Bausparen', 'amount_cents' => 60000, 'interval_unit' => 'year', 'anchor_date' => '2027-03-01']);
    });

    it('are not fixed costs but get their own line', function () {
        $data = $this->getJson('/api/v1/dashboard')->assertOk()->json('data');

        expect($data['total_cents'])->toBe(1399)
            ->and($data['subscription_count'])->toBe(1)
            ->and($data['savings_cents'])->toBe(25000 + 5000)
            ->and($data['savings_count'])->toBe(2)
            ->and(collect($data['biggest'])->pluck('subscription.name')->all())->toBe(['Netflix'])
            ->and(collect($data['categories'])->sum('count'))->toBe(1)
            // the charge still shows up as upcoming
            ->and(collect($data['upcoming'])->pluck('subscription.name')->all())->toBe(['ETF-Sparplan', 'Netflix'])
            ->and($data['upcoming'][0]['subscription']['kind'])->toBe('savings')
            ->and($this->getJson('/api/v1/dashboard?period=year')->json('data.savings_cents'))->toBe(360000);
    });

    it('have no term and no deadline', function () {
        $etf = Subscription::query()->where('name', 'ETF-Sparplan')->sole();

        expect($etf->min_term_months)->toBeNull()
            ->and($etf->hasTerm())->toBeFalse()
            ->and($etf->cancel_by_date)->toBeNull();
    });

    it('are left out of the history but part of the reserve and the month view', function () {
        expect($this->getJson('/api/v1/stats/history?months=2')->json('data.current_cents'))->toBe(1399)
            ->and($this->getJson('/api/v1/stats/reserve')->json('data.monthly_cents'))->toBe(5000)
            ->and($this->getJson('/api/v1/calendar?month=2026-11')->json('data.total_cents'))->toBe(1399 + 25000);
    });

    it('can be ended like a subscription', function () {
        $etf = Subscription::query()->where('name', 'ETF-Sparplan')->sole();

        $this->postJson("/api/v1/subscriptions/{$etf->id}/cancel", ['ends_at' => '2026-11-30'])
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');
        expect($this->getJson('/api/v1/dashboard')->json('data.savings_cents'))->toBe(5000);
    });
});

it('asks "Lohnt sich das noch?" only for subscriptions', function () {
    Subscription::factory()->for($this->user)->savings()->create(['amount_cents' => 90000]);
    Subscription::factory()->for($this->user)->installment('2027-06-01')->create(['amount_cents' => 80000]);
    $netflix = Subscription::factory()->for($this->user)->create(['name' => 'Netflix', 'amount_cents' => 1399]);

    $data = $this->getJson('/api/v1/usage-check/next')->assertOk()->json('data');

    expect($data['current']['id'])->toBe($netflix->id)
        ->and($data['progress']['total'])->toBe(1);
    expect(UsageCheck::count())->toBe(0);
});

it('filters by kind without leaking other users\' entries', function () {
    $other = User::factory()->create();
    Subscription::factory()->for($other)->savings()->create();
    $mine = Subscription::factory()->for($this->user)->savings()->create();
    Subscription::factory()->for($this->user)->create();

    $ids = collect($this->getJson('/api/v1/subscriptions?kind=savings')->assertOk()->json('data'))->pluck('id');

    expect($ids->all())->toBe([$mine->id]);
    $this->getJson('/api/v1/subscriptions?kind=rent')->assertJsonValidationErrors('kind');
});
