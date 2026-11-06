<?php

namespace App\Models;

use App\Models\Scopes\VisibleCategoryScope;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

#[Fillable(['name', 'color', 'sort'])]
#[ScopedBy(VisibleCategoryScope::class)]
class Category extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (Category $category): void {
            if ($category->user_id === null && $category->key === null && Auth::hasUser()) {
                $category->user_id = Auth::id();
            }
        });
    }

    protected function casts(): array
    {
        return [
            'sort' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isDefault(): bool
    {
        return $this->user_id === null;
    }
}
