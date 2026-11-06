<?php

namespace App\Models;

use App\Enums\IntervalUnit;
use App\Enums\NoticeUnit;
use App\Enums\SubscriptionSource;
use App\Enums\SubscriptionStatus;
use App\Models\Concerns\BelongsToUser;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property CarbonImmutable $anchor_date
 * @property ?CarbonImmutable $next_charge_date
 * @property ?CarbonImmutable $start_date
 * @property ?CarbonImmutable $term_end_date
 * @property ?CarbonImmutable $cancel_by_date
 * @property ?CarbonImmutable $ends_at
 */
#[Fillable([
    'category_id', 'name', 'amount_cents', 'interval_unit', 'interval_count', 'anchor_date',
    'start_date', 'min_term_months', 'renewal_months', 'notice_value', 'notice_unit',
    'status', 'ends_at', 'remind', 'payment_method', 'provider_url', 'notes', 'source', 'template_key',
])]
class Subscription extends Model
{
    use BelongsToUser, HasFactory, SoftDeletes;

    protected $attributes = [
        'interval_count' => 1,
        'status' => 'active',
        'remind' => true,
        'source' => 'manual',
    ];

    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
            'interval_unit' => IntervalUnit::class,
            'interval_count' => 'integer',
            'anchor_date' => 'immutable_date',
            'next_charge_date' => 'immutable_date',
            'start_date' => 'immutable_date',
            'min_term_months' => 'integer',
            'renewal_months' => 'integer',
            'notice_value' => 'integer',
            'notice_unit' => NoticeUnit::class,
            'term_end_date' => 'immutable_date',
            'cancel_by_date' => 'immutable_date',
            'status' => SubscriptionStatus::class,
            'ends_at' => 'immutable_date',
            'remind' => 'boolean',
            'source' => SubscriptionSource::class,
        ];
    }

    public function category(): BelongsTo
    {
        // Categories have their own visibility scope (defaults + own); the FK is validated on write.
        return $this->belongsTo(Category::class);
    }

    public function priceChanges(): HasMany
    {
        return $this->hasMany(PriceChange::class)->orderBy('effective_date')->orderBy('id');
    }

    /** Active subscriptions count towards totals; cancelled and paused ones do not. */
    public function scopeCounting(Builder $query): void
    {
        $query->where('status', SubscriptionStatus::Active->value);
    }

    public function isActive(): bool
    {
        return $this->status === SubscriptionStatus::Active;
    }

    /** Has a minimum term / automatic renewal, i.e. a real cancellation deadline. */
    public function hasTerm(): bool
    {
        return $this->min_term_months !== null && $this->start_date !== null;
    }
}
