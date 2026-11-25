<?php

namespace App\DTOs;

/**
 * @phpstan-type ClassTransferResultRow array{
 *     source_class_id: int,
 *     source_class_name: string,
 *     source_grade_label: string|null,
 *     target_class_id: int|null,
 *     target_class_name: string|null,
 *     target_grade_label: string|null,
 *     students_total: int,
 *     eligible_total: int,
 *     transferred: int,
 *     moved: int,
 *     already_enrolled: int,
 *     skipped_ineligible: int,
 *     skipped_conflict: int,
 *     created_target_class: bool
 * }
 * @phpstan-type ClassTransferCreatedClass array{
 *     id: int,
 *     name: string,
 *     grade: int|null,
 *     grade_label: string|null,
 *     grade_full_label: string|null
 * }
 * @phpstan-type ClassTransferTotals array{
 *     classes: int,
 *     students: int,
 *     eligible_students: int,
 *     transferred: int,
 *     moved: int,
 *     already_enrolled: int,
 *     skipped: int,
 *     skipped_ineligible: int,
 *     skipped_conflict: int,
 *     graduating: int,
 *     created_target_classes: int
 * }
 */
final readonly class ClassTransferResultDTO
{
    /**
     * @param  array{id: int, year: int, name: string, is_active: bool}  $sourceYear
     * @param  array{id: int, year: int, name: string, is_active: bool}  $targetYear
     * @param  list<ClassTransferResultRow>  $classes
     * @param  list<ClassTransferCreatedClass>  $createdClasses
     * @param  ClassTransferTotals  $totals
     */
    public function __construct(
        public array $sourceYear,
        public array $targetYear,
        public array $classes,
        public array $createdClasses,
        public array $totals,
    ) {}

    /** @return array{
     *     source_year: array{id: int, year: int, name: string, is_active: bool},
     *     target_year: array{id: int, year: int, name: string, is_active: bool},
     *     classes: list<ClassTransferResultRow>,
     *     created_classes: list<ClassTransferCreatedClass>,
     *     totals: ClassTransferTotals
     * } */
    public function toArray(): array
    {
        return [
            'source_year' => $this->sourceYear,
            'target_year' => $this->targetYear,
            'classes' => $this->classes,
            'created_classes' => $this->createdClasses,
            'totals' => $this->totals,
        ];
    }
}
