<?php

namespace App\Policies;

use App\Models\News;
use App\Models\User;

class NewsPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->isSuperAdmin();
    }

    public function view(User $actor, News $news): bool
    {
        return $actor->isSuperAdmin();
    }

    public function create(User $actor): bool
    {
        return $actor->isSuperAdmin();
    }

    public function update(User $actor, News $news): bool
    {
        return $actor->isSuperAdmin();
    }

    public function delete(User $actor, News $news): bool
    {
        return $actor->isSuperAdmin();
    }
}
