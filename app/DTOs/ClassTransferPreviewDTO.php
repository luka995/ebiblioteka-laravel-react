<?php

namespace App\DTOs;

/**
 * @phpstan-type ClassTransferPreviewTargetOption array{
 *     id: int,
 *     name: string,
 *     grade: int|null,
 *     grade_label: string|null,
 *     grade_full_label: string|null,
 *     students_count: int,
 *     is_active: bool
 * }
 * @phpstan-type ClassTransferPreviewRow array{
 *     id: int,
 *     name: string,
 *     grade: int|null,
 *     grade_label: string|null,
 *     grade_full_label: string|null,
 *     teacher_name: string|null,
 *     is_active: bool,
 *     students_count: int,
 *     eligible_students_count: int,
 *     skipped_students_count: int,
 *     already_enrolled_count: int,
 *     status: 'ready'|'needs_selection'|'needs_target'|'graduating'|'empty'|'ineligible',
 *     suggested_target_class_id: int|null,
 *     proposed_target_class_name: string|null,
 *     can_create_target: bool,
 *     target_options: list<ClassTransferPreviewTargetOption>
 * }
 * @phpstan-type ClassTransferPreviewSummary array{
 *     classes_count: int,
 *     students_count: int,
 *     eligible_students_count: int,
 *     already_enrolled_count: int,
 *     graduating_students_count: int,
 *     needs_target_count: int
 * }
 */
final readonly class ClassTransferPreviewDTO
{
    /**
     * @param  array{id: int, year: int, name: string, is_active: bool}  $sourceYear
     * @param  array{id: int, year: int, name: string, is_active: bool}|null  $targetYear
     * @param  list<ClassTransferPreviewRow>  $classes
     * @param  ClassTransferPreviewSummary  $summary
     */
    public function __construct(
        public array $sourceYear,
        public ?array $targetYear,
        public bool $targetYearExists,
        public bool $targetYearIsActive,
        public bool $canCreateMissingClasses,
        public bool $canTransfer,
        public array $classes,
        public array $summary,
    ) {}

    /** @return array{
     *     source_year: array{id: int, year: int, name: string, is_active: bool},
     *     target_year: array{id: int, year: int, name: string, is_active: bool}|null,
     *     target_year_exists: bool,
     *     target_year_is_active: bool,
     *     can_create_missing_classes: bool,
     *     can_transfer: bool,
     *     classes: list<ClassTransferPreviewRow>,
     *     summary: ClassTransferPreviewSummary
     * } */
    public function toArray(): array
    {
        return [
            'source_year' => $this->sourceYear,
            'target_year' => $this->targetYear,
            'target_year_exists' => $this->targetYearExists,
            'target_year_is_active' => $this->targetYearIsActive,
            'can_create_missing_classes' => $this->canCreateMissingClasses,
            'can_transfer' => $this->canTransfer,
            'classes' => $this->classes,
            'summary' => $this->summary,
        ];
    }
}
