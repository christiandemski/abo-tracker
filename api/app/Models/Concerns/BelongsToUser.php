<?php

namespace App\Models\Concerns;

use App\Models\Scopes\OwnedByCurrentUserScope;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

/**
 * Tenant isolation for every model with a user_id column.
 *
 * Pair each model using this trait with a policy extending App\Policies\OwnedByUserPolicy
 * and a feature test proving that user B cannot reach user A's rows.
 */
trait BelongsToUser
{
    public static function bootBelongsToUser(): void
    {
        static::addGlobalScope(new OwnedByCurrentUserScope);

        static::creating(function ($model): void {
            if ($model->user_id === null && Auth::hasUser()) {
                $model->user_id = Auth::id();
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isOwnedBy(?User $user): bool
    {
        return $user !== null && (int) $this->user_id === (int) $user->id;
    }
}
