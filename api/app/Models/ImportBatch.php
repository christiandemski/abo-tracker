<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['bank_format', 'file_name', 'period_from', 'period_to', 'rows_total', 'debits_total', 'status', 'applied_at'])]
class ImportBatch extends Model
{
    use BelongsToUser;

    protected function casts(): array
    {
        return [
            'period_from' => 'immutable_date',
            'period_to' => 'immutable_date',
            'rows_total' => 'integer',
            'debits_total' => 'integer',
            'applied_at' => 'immutable_datetime',
        ];
    }

    public function candidates(): HasMany
    {
        return $this->hasMany(ImportCandidate::class, 'batch_id');
    }
}
