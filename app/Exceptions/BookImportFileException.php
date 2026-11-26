<?php

namespace App\Exceptions;

use App\Enums\BookImportFailureEnum;
use RuntimeException;
use Throwable;

class BookImportFileException extends RuntimeException
{
    public function __construct(public readonly BookImportFailureEnum $failureCode, ?Throwable $previous = null)
    {
        parent::__construct($failureCode->getLabel(), previous: $previous);
    }
}
