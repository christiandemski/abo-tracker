<?php

namespace App\Policies;

use App\Models\Category;
use App\Models\User;

class CategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Category $category): bool
    {
        return $category->isDefault() || (int) $category->user_id === (int) $user->id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    // Default categories are shared and therefore read-only.
    public function update(User $user, Category $category): bool
    {
        return ! $category->isDefault() && (int) $category->user_id === (int) $user->id;
    }

    public function delete(User $user, Category $category): bool
    {
        return $this->update($user, $category);
    }
}
