<?php

namespace App\Http\Resources;

use App\Enums\PriceChangeStatus;
use App\Models\Subscription;
use App\Services\BillingCalculator;
use App\Services\ChargeSchedule;
use App\Services\DeadlineCalculator;
use App\Support\Fraction;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/**
 * All amounts in cents and all derived values come from the server – the app only displays them.
 *
 * @mixin Subscription
 */
class SubscriptionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $billing = app(BillingCalculator::class);
        $deadlines = app(DeadlineCalculator::class);
        $today = CarbonImmutable::today();

        $unit = $this->interval_unit;
        $count = $this->interval_count;
        $remaining = $this->isInstallment()
            ? app(ChargeSchedule::class)->remainingInstallments($this->resource, $today)
            : null;

        return [
            'id' => $this->id,
            'kind' => $this->kind->value,
            'kind_label' => $this->kind->label(),
            'name' => $this->name,
            'category' => $this->category_id ? CategoryResource::make($this->whenLoaded('category')) : null,
            'category_id' => $this->category_id,

            'amount_cents' => $this->amount_cents,
            'monthly_cents' => $billing->monthly($this->amount_cents, $unit, $count)->round(),
            'yearly_cents' => $billing->yearly($this->amount_cents, $unit, $count)->round(),
            'daily_cents' => $billing->daily($this->amount_cents, $unit, $count)->round(),
            'interval_unit' => $unit->value,
            'interval_count' => $count,
            'rhythm_label' => $billing->rhythmLabel($unit, $count),
            'is_converted' => $billing->isLongInterval($unit, $count),

            'anchor_date' => $this->anchor_date?->toDateString(),
            'next_charge_date' => $this->next_charge_date?->toDateString(),
            'days_until_charge' => $this->next_charge_date ? (int) $today->diffInDays($this->next_charge_date) : null,

            'start_date' => $this->start_date?->toDateString(),
            'min_term_months' => $this->min_term_months,
            'renewal_months' => $this->renewal_months,
            'notice_value' => $this->notice_value,
            'notice_unit' => $this->notice_unit?->value,
            'notice_label' => $deadlines->noticeLabel($this->notice_value, $this->notice_unit),
            'has_term' => $this->hasTerm(),
            'term_end_date' => $this->term_end_date?->toDateString(),
            'cancel_by_date' => $this->cancel_by_date?->toDateString(),
            'days_until_deadline' => $this->cancel_by_date ? (int) $today->diffInDays($this->cancel_by_date) : null,

            'status' => $this->status->value,
            'ends_at' => $this->ends_at?->toDateString(),
            // Installments only: open payments from today up to the last one (ends_at)
            'remaining_count' => $remaining['count'] ?? null,
            'remaining_cents' => $remaining['cents'] ?? null,
            'remind' => $this->remind,
            'payment_method' => $this->payment_method,
            'provider_url' => $this->provider_url,
            'notes' => $this->notes,
            'customer_number' => $this->customer_number,
            'provider_email' => $this->provider_email,
            'cancelled_at' => $this->cancelled_at?->toDateString(),
            'cancel_method' => $this->cancel_method,
            'kept_until' => $this->kept_until?->toDateString(),
            'is_kept' => $this->isKeptForCurrentTerm(),
            'source' => $this->source->value,
            'template_key' => $this->template_key,

            'price_history' => PriceChangeResource::collection($this->whenLoaded('priceChanges', fn () => $this->appliedPriceChanges())),
            // Change of the current price against the first recorded price, in percent (one decimal)
            'price_trend_percent' => $this->whenLoaded('priceChanges', function () {
                $first = $this->appliedPriceChanges()->first()?->old_cents;

                return $first ? (new Fraction(($this->amount_cents - $first) * 1000, $first))->round() / 10 : null;
            }),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    /** Only changes that are in effect belong to the history. */
    private function appliedPriceChanges(): Collection
    {
        return $this->priceChanges
            ->filter(fn ($p) => $p->status === PriceChangeStatus::Accepted && $p->applied_at !== null)
            ->values();
    }
}
