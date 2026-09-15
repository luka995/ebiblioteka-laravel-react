<?php

namespace App\Policies;

use App\Models\BookCopy;
use App\Models\User;

class BookCopyPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->isStaff();
    }

    public function view(User $actor, BookCopy $copy): bool
    {
        return $actor->isStaff() && $this->inScope($actor, $copy);
    }

    public function create(User $actor): bool
    {
        return $actor->isStaff();
    }

    public function update(User $actor, BookCopy $copy): bool
    {
        return $actor->isStaff() && $this->inScope($actor, $copy);
    }

    public function delete(User $actor, BookCopy $copy): bool
    {
        return $actor->isStaff() && $this->inScope($actor, $copy);
    }

    public function restore(User $actor, BookCopy $copy): bool
    {
        return $actor->isStaff() && $this->inScope($actor, $copy);
    }

    public function forceDelete(User $actor, BookCopy $copy): bool
    {
        return $actor->isStaff() && $this->inScope($actor, $copy);
    }

    public function writeOff(User $actor, BookCopy $copy): bool
    {
        return $actor->isStaff() && $this->inScope($actor, $copy);
    }

    public function syncInventory(User $actor): bool
    {
        return $actor->isStaff();
    }

    private function inScope(User $actor, BookCopy $copy): bool
    {
        return $actor->isSuperAdmin() || $actor->managesLibrary($copy->library_id);
    }
}
