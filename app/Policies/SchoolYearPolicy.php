<?php

namespace App\Policies;

use App\Models\SchoolYear;
use App\Models\User;

class SchoolYearPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->isSuperAdmin();
    }

    public function view(User $actor, SchoolYear $schoolYear): bool
    {
        return $actor->isSuperAdmin();
    }

    public function create(User $actor): bool
    {
        return $actor->isSuperAdmin();
    }

    public function update(User $actor, SchoolYear $schoolYear): bool
    {
        return $actor->isSuperAdmin();
    }

    public function delete(User $actor, SchoolYear $schoolYear): bool
    {
        return $actor->isSuperAdmin();
    }

    public function activate(User $actor, SchoolYear $schoolYear): bool
    {
        return $actor->isSuperAdmin();
    }

    public function updateSettings(User $actor): bool
    {
        return $actor->isSuperAdmin();
    }
}
