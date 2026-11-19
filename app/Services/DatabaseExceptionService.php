<?php

namespace App\Services;

use Illuminate\Database\QueryException;

/**
 * Klasifikacija gresaka iz baze, nezavisna od konkretnog kontrolera ili servisa.
 *
 * Aplikacija je PostgreSQL-only, pa se prepoznaju PostgreSQL SQLSTATE kodovi.
 */
class DatabaseExceptionService
{
    /**
     * FK ON DELETE RESTRICT (SQLSTATE 23503) znaci da zapis ima povezane redove
     * i da se ne moze obrisati dok se oni ne uklone.
     */
    public function isForeignKeyViolation(QueryException $exception): bool
    {
        return $this->hasSqlState($exception, '23503');
    }

    /**
     * Unique constraint (SQLSTATE 23505) znaci da zapis sa istim podacima vec
     * postoji.
     */
    public function isUniqueViolation(QueryException $exception): bool
    {
        return $this->hasSqlState($exception, '23505');
    }

    /**
     * Check constraint (SQLSTATE 23514) znaci da podaci ne zadovoljavaju
     * pravila definisana u bazi.
     */
    public function isCheckConstraintViolation(QueryException $exception): bool
    {
        return $this->hasSqlState($exception, '23514');
    }

    private function hasSqlState(QueryException $exception, string $sqlState): bool
    {
        return in_array($sqlState, [
            (string) $exception->getCode(),
            (string) ($exception->errorInfo[0] ?? ''),
        ], true);
    }
}
