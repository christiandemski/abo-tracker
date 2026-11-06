<?php

namespace App\Models;

use App\Enums\IntervalUnit;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Global, read-only presets without prices.
 */
#[Fillable(['key', 'name', 'category_key', 'interval_unit', 'interval_count', 'aliases', 'popular', 'sort'])]
class Template extends Model
{
    protected function casts(): array
    {
        return [
            'interval_unit' => IntervalUnit::class,
            'interval_count' => 'integer',
            'popular' => 'boolean',
            'sort' => 'integer',
        ];
    }
}
