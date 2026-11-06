<?php

use App\Models\PriceChange;
use App\Models\Subscription;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    CarbonImmutable::setTestNow('2026-11-06 12:00:00');
    $this->user = User::factory()->create();
    Sanctum::actingAs($this->user);
    $this->spotify = Subscription::factory()->for($this->user)->create(['name' => 'Spotify Duo', 'amount_cents' => 1699]);
});

afterEach(function () {
    CarbonImmutable::setTestNow();
});

function pendingChange(Subscription $s, int $new, string $date = '2026-10-22'): PriceChange
{
    $pc = new PriceChange(['subscription_id' => $s->id, 'old_cents' => $s->amount_cents, 'new_cents' => $new, 'effective_date' => $date, 'source' => 'import', 'status' => 'pending']);
    $pc->user_id = $s->user_id;
    $pc->save();

    return $pc;
}

it('lists open changes with their monthly and yearly effect', function () {
    pendingChange($this->spotify, 1799);
    $yearly = Subscription::factory()->for($this->user)->yearly(12000)->create(['name' => 'Zeitung']);
    pendingChange($yearly, 13200); // +12 €/year = +1 €/month

    $this->getJson('/api/v1/price-changes')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.subscription.name', 'Spotify Duo')
        ->assertJsonPath('data.0.percent', 5.9)
        ->assertJsonPath('data.0.monthly_diff_cents', 100)
        ->assertJsonPath('meta.pending_count', 2)
        ->assertJsonPath('meta.monthly_increase_cents', 200)
        ->assertJsonPath('meta.yearly_increase_cents', 2400);

    $this->getJson('/api/v1/dashboard')->assertJsonPath('data.notices_count', 2);
});

it('accepts a change: the new price applies and the old one stays in the history', function () {
    $pc = pendingChange($this->spotify, 1799);

    $this->postJson("/api/v1/price-changes/{$pc->id}/accept")->assertOk()->assertJsonPath('data.status', 'accepted')->assertJsonPath('data.applied', true);

    $this->getJson("/api/v1/subscriptions/{$this->spotify->id}")
        ->assertJsonPath('data.amount_cents', 1799)
        ->assertJsonPath('data.price_history.0.old_cents', 1699)
        ->assertJsonPath('data.price_trend_percent', 5.9);
    $this->postJson("/api/v1/price-changes/{$pc->id}/accept")->assertUnprocessable();
});

it('dismisses a change without touching the price', function () {
    $pc = pendingChange($this->spotify, 1799);

    $this->postJson("/api/v1/price-changes/{$pc->id}/dismiss")->assertOk()->assertJsonPath('data.status', 'dismissed');

    expect($this->spotify->fresh()->amount_cents)->toBe(1699);
    $this->getJson('/api/v1/price-changes')->assertJsonCount(0, 'data');
    $this->getJson("/api/v1/subscriptions/{$this->spotify->id}")->assertJsonPath('data.price_history', []);
});

it('schedules announced price changes and applies them on the effective date', function () {
    $this->postJson("/api/v1/subscriptions/{$this->spotify->id}/price-changes", ['new_cents' => 1999, 'effective_date' => '2027-01-01'])
        ->assertCreated()
        ->assertJsonPath('data.source', 'manual')
        ->assertJsonPath('data.in_future', true)
        ->assertJsonPath('data.applied', false);

    expect($this->spotify->fresh()->amount_cents)->toBe(1699);
    $this->getJson('/api/v1/price-changes')->assertJsonCount(1, 'data')->assertJsonPath('meta.pending_count', 0);

    CarbonImmutable::setTestNow('2027-01-01 00:05:00');
    auth()->forgetGuards();
    Artisan::call('subscriptions:refresh-dates');

    expect(Subscription::withoutGlobalScopes()->find($this->spotify->id)->amount_cents)->toBe(1999);
});

it('validates announcements', function () {
    $this->postJson("/api/v1/subscriptions/{$this->spotify->id}/price-changes", ['new_cents' => 1699, 'effective_date' => '2027-01-01'])
        ->assertUnprocessable()->assertJsonPath('errors.new_cents.0', 'Das ist der aktuelle Preis.');
});

it('lets user B neither see nor decide user A\'s price changes', function () {
    $pc = pendingChange($this->spotify, 1799);
    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/v1/price-changes')->assertJsonCount(0, 'data');
    $this->postJson("/api/v1/price-changes/{$pc->id}/accept")->assertNotFound();
    $this->postJson("/api/v1/price-changes/{$pc->id}/dismiss")->assertNotFound();
    $this->postJson("/api/v1/subscriptions/{$this->spotify->id}/price-changes", ['new_cents' => 1, 'effective_date' => '2027-01-01'])->assertNotFound();

    expect(Subscription::withoutGlobalScopes()->find($this->spotify->id)->amount_cents)->toBe(1699);
});
