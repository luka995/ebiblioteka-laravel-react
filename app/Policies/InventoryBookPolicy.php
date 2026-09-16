<?php

namespace App\Policies;

use App\Models\InventoryBook;
use App\Models\User;

class InventoryBookPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->isStaff();
    }

    public function create(User $actor): bool
    {
        return $actor->isStaff();
    }

    public function view(User $actor, InventoryBook $book): bool
    {
        return $actor->isStaff() && $this->inScope($actor, $book);
    }

    public function download(User $actor, InventoryBook $book): bool
    {
        return $actor->isStaff() && $this->inScope($actor, $book);
    }

    private function inScope(User $actor, InventoryBook $book): bool
    {
        return $actor->isSuperAdmin() || $actor->managesLibrary($book->library_id);
    }
}
