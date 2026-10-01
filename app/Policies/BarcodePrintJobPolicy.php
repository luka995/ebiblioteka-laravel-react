<?php

namespace App\Policies;

use App\Models\BarcodePrintJob;
use App\Models\User;

class BarcodePrintJobPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->isStaff();
    }

    public function create(User $actor): bool
    {
        return $actor->isStaff();
    }

    public function view(User $actor, BarcodePrintJob $job): bool
    {
        return $actor->isStaff() && $this->inScope($actor, $job);
    }

    public function download(User $actor, BarcodePrintJob $job): bool
    {
        return $actor->isStaff() && $this->inScope($actor, $job);
    }

    public function delete(User $actor, BarcodePrintJob $job): bool
    {
        return $actor->isStaff() && $this->inScope($actor, $job);
    }

    private function inScope(User $actor, BarcodePrintJob $job): bool
    {
        return $actor->isSuperAdmin() || $actor->managesLibrary($job->library_id);
    }
}
