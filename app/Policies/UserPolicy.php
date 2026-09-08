<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->isSuperAdmin() || $actor->isLibraryAdmin();
    }

    public function view(User $actor, User $target): bool
    {
        return $actor->isSuperAdmin()
            || ($actor->isLibraryAdmin() && $actor->sharesLibraryWith($target));
    }

    public function create(User $actor): bool
    {
        return $actor->isSuperAdmin() || $actor->isLibraryAdmin();
    }

    public function update(User $actor, User $target): bool
    {
        return $actor->isSuperAdmin()
            || ($actor->isLibraryAdmin() && $actor->sharesLibraryWith($target));
    }

    public function delete(User $actor, User $target): bool
    {
        return $actor->isSuperAdmin();
    }

    public function forceDelete(User $actor, User $target): bool
    {
        return $actor->isSuperAdmin();
    }

    public function manageMemberships(User $actor, User $target): bool
    {
        return $actor->isSuperAdmin();
    }

    /**
     * Trajno uklanjanje članstava korisnika iz biblioteka.
     *
     * Admin biblioteke može ukloniti korisnika iz biblioteke kojom upravlja;
     * opseg na konkretne biblioteke proverava se po zahtevu (controller/request),
     * jer policy vidi samo ciljnog korisnika.
     */
    public function removeMemberships(User $actor, User $target): bool
    {
        return $actor->isSuperAdmin()
            || ($actor->isLibraryAdmin() && $actor->sharesLibraryWith($target));
    }

    /**
     * Bulk deaktivacija/aktivacija članstava (aktivna biblioteka) — superadmin.
     */
    public function bulkManageMemberships(User $actor): bool
    {
        return $actor->isSuperAdmin();
    }

    /**
     * Bulk uklanjanje članstava (aktivna biblioteka) — superadmin ili admin biblioteke.
     */
    public function bulkRemoveMemberships(User $actor): bool
    {
        return $actor->isSuperAdmin() || $actor->isLibraryAdmin();
    }

    /**
     * Bulk soft brisanje (deaktivacija) naloga — superadmin.
     */
    public function bulkDelete(User $actor): bool
    {
        return $actor->isSuperAdmin();
    }

    /**
     * Bulk trajno brisanje naloga — superadmin.
     */
    public function bulkForceDelete(User $actor): bool
    {
        return $actor->isSuperAdmin();
    }
}
