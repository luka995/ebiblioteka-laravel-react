<?php

namespace App\Policies;

use App\Models\SchoolClass;
use App\Models\User;

class SchoolClassPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->isStaff();
    }

    public function view(User $actor, SchoolClass $class): bool
    {
        return $this->inScope($actor, $class);
    }

    public function create(User $actor): bool
    {
        return $actor->isSuperAdmin() || $actor->isLibraryAdmin();
    }

    public function update(User $actor, SchoolClass $class): bool
    {
        return $actor->isSuperAdmin() || $actor->isLibraryAdminIn($class->library_id);
    }

    public function delete(User $actor, SchoolClass $class): bool
    {
        return $this->update($actor, $class);
    }

    public function viewStudents(User $actor, SchoolClass $class): bool
    {
        return $this->inScope($actor, $class);
    }

    public function assignStudents(User $actor, SchoolClass $class): bool
    {
        return $this->inScope($actor, $class);
    }

    public function unassignStudents(User $actor, SchoolClass $class): bool
    {
        return $this->inScope($actor, $class);
    }

    public function transferStudents(User $actor): bool
    {
        return $actor->isStaff();
    }

    private function inScope(User $actor, SchoolClass $class): bool
    {
        return $actor->isSuperAdmin() || $actor->isStaffIn($class->library_id);
    }
}
