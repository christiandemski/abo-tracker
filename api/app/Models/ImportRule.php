<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['creditor_id', 'mandate_ref', 'pattern', 'subscription_id'])]
class ImportRule extends Model
{
    use BelongsToUser;

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }
}
