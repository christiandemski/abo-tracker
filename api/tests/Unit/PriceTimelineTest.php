<?php

use App\Models\PriceChange;
use App\Models\Subscription;
use App\Services\PriceTimeline;
use App\Support\Fraction;
use Carbon\CarbonImmutable;

function subscriptionWithChanges(int $amount, array $changes): Subscription
{
    $subscription = new Subscription;
    $subscription->amount_cents = $amount;
    $subscription->setRelation('priceChanges', collect($changes)->map(function (array $c, int $i) {
        $change = new PriceChange;
        $change->forceFill($c + ['id' => $i + 1, 'status' => 'accepted', 'applied_at' => null]);

        return $change;
    }));

    return $subscription;
}

function priceOn(Subscription $s, string $date): int
{
    return (new PriceTimeline)->priceOn($s, CarbonImmutable::parse($date));
}

it('returns the current amount without price changes', function () {
    expect(priceOn(subscriptionWithChanges(1399, []), '2020-01-01'))->toBe(1399);
});

it('uses the old price before an applied change', function () {
    $s = subscriptionWithChanges(1599, [
        ['old_cents' => 1199, 'new_cents' => 1399, 'effective_date' => '2025-01-01', 'applied_at' => '2025-01-01 00:05'],
        ['old_cents' => 1399, 'new_cents' => 1599, 'effective_date' => '2026-03-01', 'applied_at' => '2026-03-01 00:05'],
    ]);

    expect(priceOn($s, '2024-12-31'))->toBe(1199)
        ->and(priceOn($s, '2025-01-01'))->toBe(1399)
        ->and(priceOn($s, '2026-02-28'))->toBe(1399)
        ->and(priceOn($s, '2026-03-01'))->toBe(1599);
});

it('uses an accepted future price from its date on', function () {
    $s = subscriptionWithChanges(1399, [
        ['old_cents' => 1399, 'new_cents' => 1599, 'effective_date' => '2027-01-01'],
    ]);

    expect(priceOn($s, '2026-12-31'))->toBe(1399)
        ->and(priceOn($s, '2027-01-01'))->toBe(1599);
});

it('ignores pending and dismissed changes', function () {
    $s = subscriptionWithChanges(1399, [
        ['old_cents' => 1399, 'new_cents' => 1599, 'effective_date' => '2027-01-01', 'status' => 'pending'],
        ['old_cents' => 999, 'new_cents' => 1399, 'effective_date' => '2026-01-01', 'status' => 'dismissed', 'applied_at' => '2026-01-01'],
    ]);

    expect(priceOn($s, '2025-06-01'))->toBe(1399)
        ->and(priceOn($s, '2027-06-01'))->toBe(1399);
});

it('subtracts, compares and rounds fractions up', function () {
    expect((new Fraction(7, 2))->subtract(new Fraction(5))->round())->toBe(-2)
        ->and((new Fraction(7, 2))->ceil())->toBe(4)
        ->and((new Fraction(-7, 2))->ceil())->toBe(-3)
        ->and((new Fraction(6, 2))->ceil())->toBe(3)
        ->and((new Fraction(1, 3))->compare(new Fraction(1, 4)))->toBe(1)
        ->and((new Fraction(-1, 3))->isNegative())->toBeTrue();
});
