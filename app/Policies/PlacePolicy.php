<?php

namespace App\Policies;

use App\Models\Place;
use App\Models\User;

class PlacePolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->isSuperAdmin();
    }

    public function create(User $actor): bool
    {
        return $actor->isSuperAdmin();
    }

    public function update(User $actor, Place $place): bool
    {
        return $actor->isSuperAdmin();
    }

    public function delete(User $actor, Place $place): bool
    {
        return $actor->isSuperAdmin();
    }
}
