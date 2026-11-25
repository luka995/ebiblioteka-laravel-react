<?php

namespace App\DTOs;

use App\Enums\StudentAssignmentOutcomeEnum;

final class StudentAssignmentResultDTO
{
    private int $assigned = 0;

    private int $moved = 0;

    private int $alreadyAssigned = 0;

    public function record(StudentAssignmentOutcomeEnum $outcome): void
    {
        match ($outcome) {
            StudentAssignmentOutcomeEnum::Assigned => $this->assigned++,
            StudentAssignmentOutcomeEnum::Moved => $this->moved++,
            StudentAssignmentOutcomeEnum::AlreadyAssigned => $this->alreadyAssigned++,
        };
    }

    /**
     * @return array{assigned: int, moved: int, already_assigned: int}
     */
    public function toArray(): array
    {
        return [
            StudentAssignmentOutcomeEnum::Assigned->value => $this->assigned,
            StudentAssignmentOutcomeEnum::Moved->value => $this->moved,
            StudentAssignmentOutcomeEnum::AlreadyAssigned->value => $this->alreadyAssigned,
        ];
    }
}
