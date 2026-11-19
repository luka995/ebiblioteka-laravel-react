<?php

namespace App\Enums;

use App\Enums\Traits\EnumToArray;
use Override;

enum StudentAssignmentOutcomeEnum: string
{
    use EnumToArray;
    case Assigned = 'assigned';
    case Moved = 'moved';
    case AlreadyAssigned = 'already_assigned';

    #[Override]
    public function getLabel(): string
    {
        return match ($this) {
            self::Assigned => 'Assigned',
            self::Moved => 'Moved',
            self::AlreadyAssigned => 'Already Assigned',
        };
    }
}
