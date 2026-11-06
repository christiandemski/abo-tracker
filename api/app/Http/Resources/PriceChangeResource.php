<?php

namespace App\Http\Resources;

use App\Models\PriceChange;
use App\Support\Fraction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PriceChange
 */
class PriceChangeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'old_cents' => $this->old_cents,
            'new_cents' => $this->new_cents,
            'diff_cents' => $this->new_cents - $this->old_cents,
            // Percent with one decimal, e.g. 20.0 for +20 %
            'percent' => $this->old_cents > 0
                ? (new Fraction(($this->new_cents - $this->old_cents) * 1000, $this->old_cents))->round() / 10
                : null,
            'effective_date' => $this->effective_date->toDateString(),
            'source' => $this->source,
            'status' => $this->status->value,
        ];
    }
}
