<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['subscription_id', 'answer', 'checked_at'])]
class UsageCheck extends Model
{
    use BelongsToUser;

    public const ANSWERS = ['often', 'rare', 'never', 'skip'];

    protected function casts(): array
    {
        return ['checked_at' => 'immutable_datetime'];
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }
}
