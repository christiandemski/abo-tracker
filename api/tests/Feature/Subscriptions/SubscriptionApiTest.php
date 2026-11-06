<?php

use App\Models\Category;
use App\Models\PriceChange;
use App\Models\Subscription;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\CategorySeeder;
use Database\Seeders\TemplateSeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    CarbonImmutable::setTestNow('2026-11-05 10:00:00');
    $this->seed([CategorySeeder::class, TemplateSeeder::class]);
    $this->user = User::factory()->create();
    Sanctum::actingAs($this->user);
    $this->software = Category::query()->where('key', 'software')->value('id');
});

afterEach(function () {
    CarbonImmutable::setTestNow();
});

function adobePayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Adobe Creative Cloud',
        'amount_cents' => 14388,
        'interval_unit' => 'year',
        'anchor_date' => '2026-11-12',
        'start_date' => '2023-11-12',
        'min_term_months' => 12,
        'renewal_months' => 12,
        'notice_value' => 2,
        'notice_unit' => 'day',
        'payment_method' => 'Kreditkarte',
    ], $overrides);
}

describe('create', function () {
    it('creates a subscription with derived values from the server', function () {
        $this->postJson('/api/v1/subscriptions', adobePayload(['category_id' => $this->software]))
            ->assertCreated()
            ->assertJsonPath('data.name', 'Adobe Creative Cloud')
            ->assertJsonPath('data.amount_cents', 14388)
            ->assertJsonPath('data.monthly_cents', 1199)
            ->assertJsonPath('data.daily_cents', 39)
            ->assertJsonPath('data.rhythm_label', 'jährlich')
            ->assertJsonPath('data.is_converted', true)
            ->assertJsonPath('data.next_charge_date', '2026-11-12')
            ->assertJsonPath('data.term_end_date', '2026-11-12')
            ->assertJsonPath('data.cancel_by_date', '2026-11-10')
            ->assertJsonPath('data.days_until_deadline', 5)
            ->assertJsonPath('data.has_term', true)
            ->assertJsonPath('data.notice_label', '2 Tage')
            ->assertJsonPath('data.category.key', 'software')
            ->assertJsonPath('data.source', 'manual');

        expect(Subscription::sole()->user_id)->toBe($this->user->id);
    });

    it('treats subscriptions without minimum term as cancel anytime', function () {
        $this->postJson('/api/v1/subscriptions', [
            'name' => 'Netflix', 'amount_cents' => 1399, 'interval_unit' => 'month', 'anchor_date' => '2026-10-15',
            'template_key' => 'netflix',
        ])
            ->assertCreated()
            ->assertJsonPath('data.next_charge_date', '2026-11-15')
            ->assertJsonPath('data.cancel_by_date', null)
            ->assertJsonPath('data.has_term', false)
            ->assertJsonPath('data.source', 'template');
    });

    it('validates input with German messages', function () {
        $this->postJson('/api/v1/subscriptions', ['interval_unit' => 'decade', 'amount_cents' => 0])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'amount_cents', 'interval_unit', 'anchor_date']);

        $this->postJson('/api/v1/subscriptions', adobePayload(['notice_unit' => null]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('notice_unit');
    });

    it('rejects categories of other users', function () {
        $other = User::factory()->create();
        $foreign = Category::withoutGlobalScopes()->forceCreate([
            'user_id' => $other->id, 'name' => 'Privat', 'color' => '#9AA3B5', 'sort' => 0,
        ]);

        $this->postJson('/api/v1/subscriptions', adobePayload(['category_id' => $foreign->id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('category_id');
    });
});

describe('list', function () {
    it('filters, searches and sorts', function () {
        $streaming = Category::query()->where('key', 'streaming')->value('id');
        Subscription::factory()->for($this->user)->create(['name' => 'Netflix', 'amount_cents' => 1399, 'category_id' => $streaming, 'anchor_date' => '2026-11-15']);
        Subscription::factory()->for($this->user)->create(['name' => 'Spotify', 'amount_cents' => 1099, 'category_id' => $streaming, 'anchor_date' => '2026-11-08']);
        Subscription::factory()->for($this->user)->yearly(14388)->create(['name' => 'Adobe', 'anchor_date' => '2026-11-12']);
        Subscription::factory()->for($this->user)->cancelled('2026-11-30')->create(['name' => 'Zeitung', 'anchor_date' => '2026-11-01']);

        expect($this->getJson('/api/v1/subscriptions')->json('data.*.name'))
            ->toBe(['Spotify', 'Adobe', 'Netflix', 'Zeitung'])
            ->and($this->getJson('/api/v1/subscriptions?sort=name')->json('data.*.name'))
            ->toBe(['Adobe', 'Netflix', 'Spotify', 'Zeitung'])
            ->and($this->getJson('/api/v1/subscriptions?status=active&category='.$streaming)->json('data.*.name'))
            ->toBe(['Spotify', 'Netflix'])
            ->and($this->getJson('/api/v1/subscriptions?q=spot')->json('data.*.name'))
            ->toBe(['Spotify'])
            ->and($this->getJson('/api/v1/subscriptions?status=cancelled')->json('data.*.name'))
            ->toBe(['Zeitung']);
    });

    it('sorts by monthly price, not by charged amount', function () {
        Subscription::factory()->for($this->user)->create(['name' => 'Netflix', 'amount_cents' => 1399]);
        Subscription::factory()->for($this->user)->yearly(14388)->create(['name' => 'Adobe']); // 11,99 €/month
        Subscription::factory()->for($this->user)->create(['name' => 'Gym', 'amount_cents' => 2990]);

        expect($this->getJson('/api/v1/subscriptions?sort=price')->json('data.*.name'))->toBe(['Gym', 'Netflix', 'Adobe']);
    });
});

describe('update', function () {
    it('records manual price changes in the history', function () {
        $sub = Subscription::factory()->for($this->user)->create(['amount_cents' => 1199]);

        $this->patchJson("/api/v1/subscriptions/{$sub->id}", ['amount_cents' => 1439])
            ->assertOk()
            ->assertJsonPath('data.amount_cents', 1439)
            ->assertJsonPath('data.price_history.0.old_cents', 1199)
            ->assertJsonPath('data.price_history.0.new_cents', 1439)
            ->assertJsonPath('data.price_history.0.percent', 20)
            ->assertJsonPath('data.price_history.0.effective_date', '2026-11-05');

        $this->patchJson("/api/v1/subscriptions/{$sub->id}", ['name' => 'Umbenannt'])->assertOk();
        expect(PriceChange::count())->toBe(1);
    });

    it('recomputes dates when the term changes', function () {
        $sub = Subscription::factory()->for($this->user)->create(['anchor_date' => '2026-11-12']);

        $this->patchJson("/api/v1/subscriptions/{$sub->id}", [
            'start_date' => '2025-12-01', 'min_term_months' => 12, 'notice_value' => 1, 'notice_unit' => 'month',
        ])
            ->assertOk()
            // 12 months from 01.12.2025 → 01.12.2026, but the deadline (01.11.) has passed,
            // so the next term (monthly renewal) counts: 01.01.2027, cancel by 01.12.2026.
            ->assertJsonPath('data.term_end_date', '2027-01-01')
            ->assertJsonPath('data.cancel_by_date', '2026-12-01');
    });
});

describe('cancel and reactivate', function () {
    it('cancels with a term at the end of the term', function () {
        $sub = Subscription::factory()->for($this->user)->yearly(14388)
            ->withTerm('2023-11-12', 12, 12, 2, 'day')->create(['anchor_date' => '2026-11-12']);

        $this->postJson("/api/v1/subscriptions/{$sub->id}/cancel")
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled')
            ->assertJsonPath('data.ends_at', '2026-11-11')
            ->assertJsonPath('data.next_charge_date', null)
            ->assertJsonPath('data.cancel_by_date', null);

        $this->postJson("/api/v1/subscriptions/{$sub->id}/reactivate")
            ->assertOk()
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.ends_at', null)
            ->assertJsonPath('data.next_charge_date', '2026-11-12');
    });

    it('cancels without term before the next charge', function () {
        $sub = Subscription::factory()->for($this->user)->create(['anchor_date' => '2026-10-15']);

        $this->postJson("/api/v1/subscriptions/{$sub->id}/cancel")
            ->assertOk()
            ->assertJsonPath('data.ends_at', '2026-11-14')
            ->assertJsonPath('data.next_charge_date', null);
    });
});

it('soft deletes subscriptions', function () {
    $sub = Subscription::factory()->for($this->user)->create();

    $this->deleteJson("/api/v1/subscriptions/{$sub->id}")->assertNoContent();

    expect(Subscription::count())->toBe(0)
        ->and(Subscription::withTrashed()->count())->toBe(1);
    $this->getJson("/api/v1/subscriptions/{$sub->id}")->assertNotFound();
});

describe('tenant isolation', function () {
    it('lets user B neither list, read, change, cancel nor delete user A\'s subscriptions', function () {
        $sub = Subscription::factory()->for($this->user)->create(['name' => 'Nur Alice']);
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/subscriptions')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson("/api/v1/subscriptions/{$sub->id}")->assertNotFound();
        $this->patchJson("/api/v1/subscriptions/{$sub->id}", ['name' => 'x'])->assertNotFound();
        $this->postJson("/api/v1/subscriptions/{$sub->id}/cancel")->assertNotFound();
        $this->postJson("/api/v1/subscriptions/{$sub->id}/reactivate")->assertNotFound();
        $this->deleteJson("/api/v1/subscriptions/{$sub->id}")->assertNotFound();

        expect(Subscription::withoutGlobalScopes()->find($sub->id))
            ->name->toBe('Nur Alice')
            ->deleted_at->toBeNull();
    });

    it('requires a verified address', function () {
        Sanctum::actingAs(User::factory()->unverified()->create());

        $this->getJson('/api/v1/subscriptions')->assertForbidden();
    });
});
