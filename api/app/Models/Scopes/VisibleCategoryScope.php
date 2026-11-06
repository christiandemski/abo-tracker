<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;

/**
 * Categories: default ones (user_id = null) plus the authenticated user's own.
 */
class VisibleCategoryScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $column = $model->qualifyColumn('user_id');

        if (Auth::hasUser()) {
            $builder->where(fn (Builder $q) => $q->whereNull($column)->orWhere($column, Auth::id()));

            return;
        }

        if (App::runningInConsole() && ! App::runningUnitTests()) {
            return;
        }

        $builder->whereNull($column);
    }
}
