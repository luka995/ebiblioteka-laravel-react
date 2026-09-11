<?php

namespace App\Policies;

use App\Models\Category;
use App\Models\User;

class CategoryPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->isSuperAdmin() || $actor->isLibraryAdmin();
    }

    public function view(User $actor, Category $category): bool
    {
        return $actor->isSuperAdmin() || $actor->isLibraryAdmin();
    }

    public function create(User $actor): bool
    {
        return $actor->isSuperAdmin() || $actor->isLibraryAdmin();
    }

    public function update(User $actor, Category $category): bool
    {
        return $actor->isSuperAdmin() || $actor->isLibraryAdmin();
    }

    public function delete(User $actor, Category $category): bool
    {
        return $actor->isSuperAdmin() || $actor->isLibraryAdmin();
    }
}
