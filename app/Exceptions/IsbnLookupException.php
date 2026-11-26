<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

class IsbnLookupException extends RuntimeException
{
    public function __construct(public readonly string $reason, ?Throwable $previous = null)
    {
        parent::__construct("ISBN lookup failed ({$reason}).", previous: $previous);
    }
}
