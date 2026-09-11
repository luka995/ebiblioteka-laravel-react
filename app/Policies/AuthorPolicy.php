<?php

namespace App\Policies;

use App\Models\Author;
use App\Models\User;

class AuthorPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->isSuperAdmin() || $actor->isLibraryAdmin();
    }

    public function view(User $actor, Author $author): bool
    {
        return $actor->isSuperAdmin() || $actor->isLibraryAdmin();
    }

    public function create(User $actor): bool
    {
        return $actor->isSuperAdmin() || $actor->isLibraryAdmin();
    }

    public function update(User $actor, Author $author): bool
    {
        return $actor->isSuperAdmin() || $actor->isLibraryAdmin();
    }

    public function delete(User $actor, Author $author): bool
    {
        return $actor->isSuperAdmin() || $actor->isLibraryAdmin();
    }
}
