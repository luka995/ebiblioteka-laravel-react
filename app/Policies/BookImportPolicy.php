<?php

namespace App\Policies;

use App\Models\BookImport;
use App\Models\User;

class BookImportPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->isStaff();
    }

    public function create(User $actor): bool
    {
        return $actor->isStaff();
    }

    public function view(User $actor, BookImport $import): bool
    {
        return $this->inScope($actor, $import);
    }

    public function start(User $actor, BookImport $import): bool
    {
        return $this->inScope($actor, $import);
    }

    public function retry(User $actor, BookImport $import): bool
    {
        return $this->inScope($actor, $import);
    }

    public function updateRow(User $actor, BookImport $import): bool
    {
        return $this->inScope($actor, $import);
    }

    private function inScope(User $actor, BookImport $import): bool
    {
        return $actor->isSuperAdmin() || $actor->isStaffIn($import->library_id);
    }
}
