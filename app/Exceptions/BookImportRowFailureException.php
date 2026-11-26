<?php

namespace App\Exceptions;

use App\Enums\BookImportFailureEnum;
use RuntimeException;

class BookImportRowFailureException extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $diagnostics
     */
    public function __construct(
        public readonly BookImportFailureEnum $failure,
        public readonly ?string $field = null,
        public readonly array $diagnostics = [],
    ) {
        parent::__construct($failure->getLabel());
    }
}
