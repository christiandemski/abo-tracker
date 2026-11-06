<?php

namespace App\Console\Commands;

use App\Models\Scopes\OwnedByCurrentUserScope;
use App\Models\Subscription;
use App\Services\PriceChanges;
use App\Services\SubscriptionDates;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('subscriptions:refresh-dates')]
#[Description('Recompute next charge, term end and cancel-by dates for all subscriptions')]
class RefreshSubscriptionDates extends Command
{
    public function handle(SubscriptionDates $dates, PriceChanges $priceChanges): int
    {
        $today = CarbonImmutable::today();
        $changed = 0;

        // Accepted / announced price changes whose date has come
        $applied = $priceChanges->applyDue($today);

        // Runs for all users, so the tenant scope is lifted explicitly.
        Subscription::query()->withoutGlobalScope(OwnedByCurrentUserScope::class)->chunkById(200, function ($subscriptions) use ($dates, $today, &$changed) {
            foreach ($subscriptions as $subscription) {
                $dates->apply($subscription, $today);
                if ($subscription->isDirty()) {
                    $subscription->saveQuietly();
                    $changed++;
                }
            }
        });

        $this->info("Updated {$changed} subscription(s), applied {$applied} price change(s).");

        return self::SUCCESS;
    }
}
