<?php

use App\Models\Subscription;
use App\Models\UsageCheck;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    CarbonImmutable::setTestNow('2026-11-06 10:00:00');
    Carbon::setTestNow('2026-11-06 10:00:00');
    $this->user = User::factory()->create();
    Sanctum::actingAs($this->user);
    $make = fn (string $name, int $cents, string $unit = 'month') => Subscription::factory()->for($this->user)
        ->create(['name' => $name, 'amount_cents' => $cents, 'interval_unit' => $unit]);
    $this->gym = $make('Fitnessstudio', 2990);
    $this->netflix = $make('Netflix', 1399);
    $this->strava = $make('Strava', 5999, 'year'); // 5,00 €/month
    Subscription::factory()->for($this->user)->cancelled('2026-11-30')->create(['name' => 'Gekündigt', 'amount_cents' => 9999]);
});

afterEach(function () {
    CarbonImmutable::setTestNow();
    Carbon::setTestNow();
});

function answer(int $id, string $answer): TestResponse
{
    return test()->postJson('/api/v1/usage-check', ['subscription_id' => $id, 'answer' => $answer]);
}

it('asks active subscriptions, most expensive first', function () {
    $this->getJson('/api/v1/usage-check/next')
        ->assertOk()
        ->assertJsonPath('data.current.name', 'Fitnessstudio')
        ->assertJsonPath('data.current.yearly_cents', 35880)
        ->assertJsonPath('data.progress', ['answered' => 0, 'total' => 3])
        ->assertJsonPath('data.due', true)
        ->assertJsonPath('data.done', false);
});

it('collects rare/never answers as candidates with yearly savings', function () {
    answer($this->gym->id, 'never')->assertCreated()->assertJsonPath('data.current.name', 'Netflix');
    answer($this->netflix->id, 'often');
    answer($this->strava->id, 'rare')
        ->assertJsonPath('data.done', true)
        ->assertJsonPath('data.current', null)
        ->assertJsonPath('data.progress.answered', 3)
        ->assertJsonPath('data.candidates.0.name', 'Fitnessstudio')
        ->assertJsonPath('data.candidates.0.answer', 'never')
        ->assertJsonPath('data.candidates.1.name', 'Strava')
        ->assertJsonPath('data.savings_yearly_cents', 35880 + 5999)
        ->assertJsonPath('data.due', false);
});

it('treats "skip" as answered but not as candidate', function () {
    answer($this->gym->id, 'skip')->assertJsonPath('data.current.name', 'Netflix')->assertJsonPath('data.candidates', []);
});

it('starts a new round after 90 days and on restart', function () {
    answer($this->gym->id, 'never');

    $this->postJson('/api/v1/usage-check/restart')->assertOk()->assertJsonPath('data.progress.answered', 0);

    answer($this->gym->id, 'never');
    CarbonImmutable::setTestNow('2027-02-10 10:00:00');
    Carbon::setTestNow('2027-02-10 10:00:00');

    $this->getJson('/api/v1/usage-check/next')
        ->assertJsonPath('data.progress.answered', 0)
        ->assertJsonPath('data.due', true)
        ->assertJsonPath('data.candidates', []);
});

it('validates answers and ownership', function () {
    answer($this->gym->id, 'maybe')->assertUnprocessable()->assertJsonValidationErrors('answer');

    $foreign = Subscription::factory()->create();
    answer($foreign->id, 'never')->assertUnprocessable()->assertJsonValidationErrors('subscription_id');
});

it('keeps checks per user', function () {
    answer($this->gym->id, 'never');
    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/v1/usage-check/next')->assertJsonPath('data.progress.total', 0)->assertJsonPath('data.candidates', []);
    $this->postJson('/api/v1/usage-check/restart')->assertOk();

    expect(UsageCheck::withoutGlobalScopes()->count())->toBe(1);
});
