<?php

namespace App\Policies;

use App\Enums\LibraryRequestStatus;
use App\Models\LibraryRequest;
use App\Models\User;

class LibraryRequestPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->isSuperAdmin();
    }

    public function view(User $actor, LibraryRequest $libraryRequest): bool
    {
        return $actor->isSuperAdmin();
    }

    public function approve(User $actor, LibraryRequest $libraryRequest): bool
    {
        return $actor->isSuperAdmin() && $libraryRequest->status === LibraryRequestStatus::Pending;
    }

    public function reject(User $actor, LibraryRequest $libraryRequest): bool
    {
        return $actor->isSuperAdmin() && $libraryRequest->status === LibraryRequestStatus::Pending;
    }
}
