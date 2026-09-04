<?php

namespace App\Policies;

use App\Models\Tag;
use App\Models\User;

class TagPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->isSuperAdmin() || $actor->isLibraryAdmin();
    }

    public function view(User $actor, Tag $tag): bool
    {
        return $actor->isSuperAdmin()
            || ($actor->isLibraryAdmin() && $actor->managesLibrary($tag->library_id));
    }

    public function create(User $actor): bool
    {
        return $actor->isSuperAdmin() || $actor->isLibraryAdmin();
    }

    public function update(User $actor, Tag $tag): bool
    {
        return $actor->isSuperAdmin()
            || ($actor->isLibraryAdmin() && $actor->managesLibrary($tag->library_id));
    }

    public function delete(User $actor, Tag $tag): bool
    {
        return $actor->isSuperAdmin()
            || ($actor->isLibraryAdmin() && $actor->managesLibrary($tag->library_id));
    }
}
