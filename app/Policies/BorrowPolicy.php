<?php

namespace App\Policies;

use App\Models\Borrow;
use App\Models\User;

class BorrowPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->isStaffAnywhere();
    }

    public function view(User $actor, Borrow $borrow): bool
    {
        return $actor->id === $borrow->user_id
            || $actor->isSuperAdmin()
            || $actor->isStaffIn($borrow->library_id);
    }

    public function create(User $actor): bool
    {
        return $actor->isStaffAnywhere();
    }

    public function returnBook(User $actor, Borrow $borrow): bool
    {
        return ! $borrow->returned
            && ($actor->isSuperAdmin() || $actor->isStaffIn($borrow->library_id));
    }
}
