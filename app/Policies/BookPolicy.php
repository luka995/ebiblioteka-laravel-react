<?php

namespace App\Policies;

use App\Models\Book;
use App\Models\User;

class BookPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->isStaff();
    }

    public function view(User $actor, Book $book): bool
    {
        return $actor->isStaff() && $this->inScope($actor, $book);
    }

    public function create(User $actor): bool
    {
        return $actor->isStaff();
    }

    public function update(User $actor, Book $book): bool
    {
        return $actor->isStaff() && $this->inScope($actor, $book);
    }

    public function delete(User $actor, Book $book): bool
    {
        return $actor->isStaff() && $this->inScope($actor, $book);
    }

    public function restore(User $actor, Book $book): bool
    {
        return $actor->isStaff() && $this->inScope($actor, $book);
    }

    public function forceDelete(User $actor, Book $book): bool
    {
        return $actor->isStaff() && $this->inScope($actor, $book);
    }

    private function inScope(User $actor, Book $book): bool
    {
        return $actor->isSuperAdmin() || $actor->managesLibrary($book->library_id);
    }
}
