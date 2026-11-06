<?php

namespace App\Services;

use App\Enums\PriceChangeStatus;
use App\Models\PriceChange;
use App\Models\Scopes\OwnedByCurrentUserScope;
use App\Models\Subscription;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Accepting sets the new price from effective_date on; the old price stays in the history.
 * Future changes are applied by the daily run (subscriptions:refresh-dates).
 */
class PriceChanges
{
    public function accept(PriceChange $change, ?CarbonImmutable $today = null): PriceChange
    {
        $today ??= CarbonImmutable::today();

        DB::transaction(function () use ($change, $today) {
            $change->status = PriceChangeStatus::Accepted;
            $change->save();
            $this->applyIfDue($change, $today);
        });

        return $change->refresh();
    }

    public function dismiss(PriceChange $change): PriceChange
    {
        $change->forceFill(['status' => PriceChangeStatus::Dismissed])->save();

        return $change;
    }

    /** Announced price change entered by the user ("ab 01.01.2027 32,90 €"). */
    public function announce(Subscription $subscription, int $newCents, CarbonImmutable $effective, ?CarbonImmutable $today = null): PriceChange
    {
        $today ??= CarbonImmutable::today();

        return DB::transaction(function () use ($subscription, $newCents, $effective, $today) {
            $change = new PriceChange([
                'subscription_id' => $subscription->id,
                'old_cents' => $subscription->amount_cents,
                'new_cents' => $newCents,
                'effective_date' => $effective->toDateString(),
                'source' => 'manual',
                'status' => PriceChangeStatus::Accepted,
            ]);
            $change->user_id = $subscription->user_id;
            $change->save();
            $this->applyIfDue($change, $today);

            return $change->refresh();
        });
    }

    /** Daily: write accepted changes whose date has come to the subscription. */
    public function applyDue(?CarbonImmutable $today = null): int
    {
        $today ??= CarbonImmutable::today();
        $count = 0;

        PriceChange::query()
            ->withoutGlobalScope(OwnedByCurrentUserScope::class)
            ->where('status', PriceChangeStatus::Accepted->value)
            ->whereNull('applied_at')
            ->whereDate('effective_date', '<=', $today->toDateString())
            ->orderBy('effective_date')
            ->each(function (PriceChange $change) use ($today, &$count) {
                if ($this->applyIfDue($change, $today)) {
                    $count++;
                }
            });

        return $count;
    }

    private function applyIfDue(PriceChange $change, CarbonImmutable $today): bool
    {
        if ($change->applied_at !== null || $change->effective_date->greaterThan($today)) {
            return false;
        }

        $subscription = Subscription::query()
            ->withoutGlobalScope(OwnedByCurrentUserScope::class)
            ->find($change->subscription_id);

        if ($subscription) {
            $subscription->amount_cents = $change->new_cents;
            $subscription->save();
        }

        $change->forceFill(['applied_at' => now()])->save();

        return true;
    }
}
