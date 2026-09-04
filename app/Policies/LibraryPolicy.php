<?php

namespace App\Policies;

use App\Models\Library;
use App\Models\User;

class LibraryPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->isSuperAdmin() || $actor->isLibraryAdmin();
    }

    public function view(User $actor, Library $library): bool
    {
        return $actor->isSuperAdmin()
            || ($actor->isLibraryAdmin() && $actor->managesLibrary($library->id));
    }

    public function create(User $actor): bool
    {
        return $actor->isSuperAdmin();
    }

    public function update(User $actor, Library $library): bool
    {
        return $actor->isSuperAdmin();
    }

    public function delete(User $actor, Library $library): bool
    {
        return $actor->isSuperAdmin();
    }

    public function restore(User $actor, Library $library): bool
    {
        return $actor->isSuperAdmin();
    }
}
