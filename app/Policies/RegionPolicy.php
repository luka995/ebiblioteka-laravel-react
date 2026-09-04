<?php

namespace App\Policies;

use App\Models\Region;
use App\Models\User;

class RegionPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->isSuperAdmin();
    }

    public function create(User $actor): bool
    {
        return $actor->isSuperAdmin();
    }

    public function update(User $actor, Region $region): bool
    {
        return $actor->isSuperAdmin();
    }

    public function delete(User $actor, Region $region): bool
    {
        return $actor->isSuperAdmin();
    }
}
