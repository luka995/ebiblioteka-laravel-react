<?php

namespace App\Exceptions;

use App\Models\Reservation;
use Exception;

/**
 * Korisnik ima aktivnu rezervaciju za drugi primerak istog naslova.
 *
 * Izdavanje se ne izvrsava automatski: osoblje mora da potvrdi da se
 * rezervacija realizuje skeniranim primerkom, a rezervisani primerak
 * oslobadja.
 */
class ReservationExchangeRequiredException extends Exception
{
    public function __construct(public readonly Reservation $reservation)
    {
        parent::__construct('Reservation exchange confirmation is required.');
    }
}
