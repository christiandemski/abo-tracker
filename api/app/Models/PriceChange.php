<?php

namespace App\Models;

use App\Enums\PriceChangeStatus;
use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['subscription_id', 'old_cents', 'new_cents', 'effective_date', 'source', 'status'])]
class PriceChange extends Model
{
    use BelongsToUser;

    protected function casts(): array
    {
        return [
            'old_cents' => 'integer',
            'new_cents' => 'integer',
            'effective_date' => 'immutable_date',
            'status' => PriceChangeStatus::class,
        ];
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }
}
