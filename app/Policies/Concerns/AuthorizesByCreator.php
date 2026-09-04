<?php

namespace App\Policies\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Ownership pattern za buduce modele (Book, Borrowing) sa kolonom `created_by`.
 *
 * Pravilo: kreator resursa ili admin (superadmin + library_admin) moze da
 * upravlja resursom. Koristi se unutar Policy metoda, npr.:
 *
 *     public function update(User $actor, Book $book): bool
 *     {
 *         return $this->canManage($actor, $book);
 *     }
 */
trait AuthorizesByCreator
{
    protected function isCreator(User $actor, Model $model): bool
    {
        return $model->getAttribute('created_by') === $actor->getKey();
    }

    protected function canManage(User $actor, Model $model): bool
    {
        return $actor->isAdmin() || $this->isCreator($actor, $model);
    }
}
