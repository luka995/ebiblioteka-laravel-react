<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->isSuperAdmin() || $actor->isLibraryAdmin();
    }

    public function view(User $actor, User $target): bool
    {
        return $actor->isSuperAdmin()
            || ($actor->isLibraryAdmin() && $actor->sharesLibraryWith($target));
    }

    public function create(User $actor): bool
    {
        return $actor->isSuperAdmin() || $actor->isLibraryAdmin();
    }

    public function update(User $actor, User $target): bool
    {
        return $actor->isSuperAdmin()
            || ($actor->isLibraryAdmin() && $actor->sharesLibraryWith($target));
    }

    public function delete(User $actor, User $target): bool
    {
        return $actor->isSuperAdmin();
    }

    public function forceDelete(User $actor, User $target): bool
    {
        return $actor->isSuperAdmin();
    }

    public function manageMemberships(User $actor, User $target): bool
    {
        return $actor->isSuperAdmin();
    }
}
