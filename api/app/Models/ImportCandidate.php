<?php

namespace App\Models;

use App\Enums\IntervalUnit;
use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportCandidate extends Model
{
    use BelongsToUser;

    public const STATUS_NEW = 'new';

    public const STATUS_DUPLICATE = 'duplicate';

    public const STATUS_PRICE_CHANGE = 'price_change';

    protected $guarded = ['id', 'user_id'];

    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
            'interval_unit' => IntervalUnit::class,
            'interval_count' => 'integer',
            'occurrences' => 'integer',
            'first_date' => 'immutable_date',
            'last_date' => 'immutable_date',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ImportBatch::class, 'batch_id');
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
