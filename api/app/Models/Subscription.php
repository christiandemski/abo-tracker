<?php

namespace App\Models;

use App\Enums\IntervalUnit;
use App\Enums\NoticeUnit;
use App\Enums\SubscriptionKind;
use App\Enums\SubscriptionSource;
use App\Enums\SubscriptionStatus;
use App\Models\Concerns\BelongsToUser;
use App\Services\SubscriptionDates;
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
    'category_id', 'kind', 'name', 'amount_cents', 'interval_unit', 'interval_count', 'anchor_date',
    'start_date', 'min_term_months', 'renewal_months', 'notice_value', 'notice_unit',
    'status', 'ends_at', 'remind', 'payment_method', 'provider_url', 'notes', 'source', 'template_key',
    'customer_number', 'provider_email',
])]
class Subscription extends Model
{
    use BelongsToUser, HasFactory, SoftDeletes;

    protected static function booted(): void
    {
        // Derived dates are always recomputed from the source fields.
        static::saving(fn (Subscription $subscription) => app(SubscriptionDates::class)->apply($subscription));
    }

    protected $attributes = [
        'kind' => 'subscription',
        'interval_count' => 1,
        'status' => 'active',
        'remind' => true,
        'source' => 'manual',
    ];

    protected function casts(): array
    {
        return [
            'kind' => SubscriptionKind::class,
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
            'cancelled_at' => 'immutable_date',
            'kept_until' => 'immutable_date',
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

    /** "Behalten" chosen for the current term: no deadline banner, card or reminder. */
    public function isKeptForCurrentTerm(): bool
    {
        return self::isKeptTerm($this->kept_until, $this->term_end_date);
    }

    /**
     * kept_until is the renewal day of the term the user chose to keep. A deadline
     * belongs to that term if its renewal day is not later. Comparing the deadline
     * itself would be wrong: the next term's deadline can lie before kept_until
     * (monthly renewal, or a notice period longer than the renewal period).
     */
    public static function isKeptTerm(?CarbonImmutable $keptUntil, ?CarbonImmutable $termEnd): bool
    {
        return $keptUntil !== null
            && $termEnd !== null
            && $termEnd->lessThanOrEqualTo($keptUntil);
    }

    /** Has a minimum term / automatic renewal, i.e. a real cancellation deadline. Only subscriptions do. */
    public function hasTerm(): bool
    {
        return $this->isSubscription() && $this->min_term_months !== null && $this->start_date !== null;
    }

    /** A real subscription or contract: cancellable, with the saving features. */
    public function isSubscription(): bool
    {
        return $this->kind === SubscriptionKind::Subscription;
    }

    public function isInstallment(): bool
    {
        return $this->kind === SubscriptionKind::Installment;
    }

    /** Savings are shown, but not counted as fixed costs. */
    public function isSavings(): bool
    {
        return $this->kind === SubscriptionKind::Savings;
    }
}
