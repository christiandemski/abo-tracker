<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;

/**
 * Restricts every query to rows of the authenticated user.
 *
 * - Authenticated request: only the user's own rows.
 * - Console (scheduler, artisan) without a user: unrestricted, so jobs can work across users.
 * - Anything else without a user: no rows at all (fail closed).
 */
class OwnedByCurrentUserScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $column = $model->qualifyColumn('user_id');

        if (Auth::hasUser()) {
            $builder->where($column, Auth::id());

            return;
        }

        if (App::runningInConsole() && ! App::runningUnitTests()) {
            return;
        }

        $builder->whereRaw('1 = 0');
    }
}
