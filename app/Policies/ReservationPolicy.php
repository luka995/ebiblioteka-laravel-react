<?php

namespace App\Policies;

use App\Models\Reservation;
use App\Models\User;

class ReservationPolicy
{
    /**
     * Uvid u sve rezervacije na nivou biblioteke ima osoblje.
     */
    public function viewAny(User $actor): bool
    {
        return $actor->isStaff();
    }

    /**
     * Svaki prijavljeni korisnik moze da kreira sopstvenu rezervaciju.
     */
    public function create(User $actor): bool
    {
        return true;
    }

    /**
     * Rezervaciju moze da otkaze njen vlasnik ili osoblje biblioteke u kojoj
     * je rezervacija napravljena.
     */
    public function cancel(User $actor, Reservation $reservation): bool
    {
        if ($actor->id === $reservation->user_id) {
            return true;
        }

        return $actor->isSuperAdmin() || $actor->isStaffIn($reservation->library_id);
    }

    /**
     * Rezervaciju moze da realizuje osoblje biblioteke u kojoj je napravljena.
     */
    public function issue(User $actor, Reservation $reservation): bool
    {
        return $reservation->isActive()
            && ($actor->isSuperAdmin() || $actor->isStaffIn($reservation->library_id));
    }
}
