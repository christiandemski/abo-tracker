<?php

namespace App\Services;

use App\Enums\PriceChangeStatus;
use App\Models\PriceChange;
use App\Models\Subscription;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Price of a subscription on a given day, from its accepted price changes.
 *
 * - Already applied changes: before their effective date the old price applied.
 * - Accepted but not yet applied (future) changes: from their date the new price applies.
 * - Otherwise the current amount_cents (also covers manual edits).
 *
 * Load the priceChanges relation beforehand to avoid one query per subscription.
 */
class PriceTimeline
{
    public function priceOn(Subscription $subscription, CarbonImmutable $date): int
    {
        $date = $date->startOfDay();

        /** @var Collection<int, PriceChange> $accepted */
        $accepted = $subscription->priceChanges
            ->filter(fn (PriceChange $c) => $c->status === PriceChangeStatus::Accepted)
            ->sortBy(fn (PriceChange $c) => [$c->effective_date->toDateString(), $c->id])
            ->values();

        // Latest pending-application change that has already started on $date
        $future = $accepted
            ->filter(fn (PriceChange $c) => $c->applied_at === null && $c->effective_date->lessThanOrEqualTo($date))
            ->last();
        if ($future !== null) {
            return $future->new_cents;
        }

        // First applied change that lies after $date: its old price was valid then
        $later = $accepted
            ->first(fn (PriceChange $c) => $c->applied_at !== null && $c->effective_date->greaterThan($date));
        if ($later !== null) {
            return $later->old_cents;
        }

        return $subscription->amount_cents;
    }
}
