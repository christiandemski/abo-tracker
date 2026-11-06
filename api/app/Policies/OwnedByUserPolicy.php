<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Base policy for models using App\Models\Concerns\BelongsToUser.
 * The global scope already hides foreign rows; this is the second line of defense.
 */
abstract class OwnedByUserPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function view(User $user, Model $model): bool
    {
        return $model->isOwnedBy($user);
    }

    public function update(User $user, Model $model): bool
    {
        return $model->isOwnedBy($user);
    }

    public function delete(User $user, Model $model): bool
    {
        return $model->isOwnedBy($user);
    }
}
