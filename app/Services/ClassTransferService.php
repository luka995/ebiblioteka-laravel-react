<?php

namespace App\Services;

use App\DTOs\ClassTransferPreviewDTO;
use App\DTOs\ClassTransferResultDTO;
use App\Enums\ClassTransferRowStatusEnum;
use App\Enums\LibraryRole;
use App\Enums\SchoolGrade;
use App\Models\ClassEnrollment;
use App\Models\Library;
use App\Models\SchoolClass;
use App\Models\SchoolYear;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * @phpstan-import-type ClassTransferPreviewRow from ClassTransferPreviewDTO
 * @phpstan-import-type ClassTransferPreviewTargetOption from ClassTransferPreviewDTO
 * @phpstan-import-type ClassTransferPreviewSummary from ClassTransferPreviewDTO
 * @phpstan-import-type ClassTransferResultRow from ClassTransferResultDTO
 * @phpstan-import-type ClassTransferCreatedClass from ClassTransferResultDTO
 * @phpstan-import-type ClassTransferTotals from ClassTransferResultDTO
 *
 * @phpstan-type ClassTransferPreviewSnapshot array{
 *   target_year: SchoolYear|null,
 *   source_classes: Collection<int, SchoolClass>,
 *   target_classes: Collection<int, SchoolClass>,
 *   source_enrollments: Collection<int, Collection<int, ClassEnrollment>>,
 *   target_enrollments: Collection<int, ClassEnrollment>
 * }
 * @phpstan-type ClassTransferSnapshot array{
 *   source_classes: Collection<int, SchoolClass>,
 *   target_classes: Collection<int, SchoolClass>,
 *   source_enrollments: Collection<int, Collection<int, ClassEnrollment>>,
 *   target_enrollments: Collection<int, ClassEnrollment>
 * }
 * @phpstan-type ClassTransferClassOutcome array{
 *   row: ClassTransferResultRow|null,
 *   created_class: ClassTransferCreatedClass|null,
 *   created_target_class: SchoolClass|null,
 *   graduating_students: int
 * }
 * @phpstan-type ResolvedTransferTarget array{target_class: SchoolClass, created: bool}
 * @phpstan-type ClassifiedTransferEnrollments array{
 *   to_assign: list<int>,
 *   already_enrolled: int,
 *   skipped_conflict: int
 * }
 * @phpstan-type ClassTransferAssignmentOutcome array{
 *   transferred: int,
 *   moved: int,
 *   already_enrolled: int
 * }
 */
class ClassTransferService
{
    public function __construct(
        private readonly SchoolYearService $yearsService,
        private readonly SchoolClassService $classesService,
    ) {}

    public function defaultSourceYearId(Library $library): ?int
    {
        $counts = SchoolClass::query()
            ->where('library_id', $library->id)
            ->selectRaw('school_year_id, count(*) as aggregate')
            ->groupBy('school_year_id')
            ->pluck('aggregate', 'school_year_id');

        $active = $this->yearsService->active();

        if ($active === null) {
            return null;
        }

        $previous = $this->yearsService->previousYear($active);

        if ($previous !== null && (int) $counts->get($previous->id, 0) > 0) {
            return $previous->id;
        }

        if ((int) $counts->get($active->id, 0) > 0) {
            return $active->id;
        }

        $latest = SchoolYear::query()
            ->where('year', '<=', $active->year)
            ->orderByDesc('year')
            ->get()
            ->first(fn (SchoolYear $year): bool => (int) $counts->get($year->id, 0) > 0);

        return $latest?->id ?? $active->id;
    }

    public function preview(Library $library, SchoolYear $source): ClassTransferPreviewDTO
    {
        $this->assertSourceYearWithinActiveTransferWindow($source);

        $snapshot = $this->loadPreviewSnapshot($library, $source);
        $rows = $this->buildPreviewRows($snapshot);
        $summary = $this->buildPreviewSummary($rows);

        $canCreateMissingClasses = collect($rows)->contains(fn (array $row): bool => $row['can_create_target']);
        $hasBlockedRows = collect($rows)->contains(fn (array $row): bool => $row['status'] === ClassTransferRowStatusEnum::NeedsTarget->value
            && ! $row['can_create_target']);

        return new ClassTransferPreviewDTO(
            sourceYear: $this->yearPayload($source),
            targetYear: $snapshot['target_year'] === null ? null : $this->yearPayload($snapshot['target_year']),
            targetYearExists: $snapshot['target_year'] !== null,
            targetYearIsActive: $snapshot['target_year'] !== null && $this->yearsService->isActive($snapshot['target_year']),
            canCreateMissingClasses: $canCreateMissingClasses,
            canTransfer: $snapshot['target_year'] !== null && $summary['eligible_students_count'] > 0 && ! $hasBlockedRows,
            classes: $rows,
            summary: $summary,
        );
    }

    /**
     * @return ClassTransferPreviewSnapshot
     */
    private function loadPreviewSnapshot(Library $library, SchoolYear $source): array
    {
        $target = $this->yearsService->nextYear($source);

        $sourceClasses = SchoolClass::query()
            ->where('library_id', $library->id)
            ->where('school_year_id', $source->id)
            ->orderBy('grade')
            ->orderBy('name')
            ->get();

        $sourceEnrollments = ClassEnrollment::query()
            ->whereIn('class_id', $sourceClasses->pluck('id'))
            ->with([
                'user' => fn ($query) => $query->with([
                    'libraries' => fn ($libraries) => $libraries
                        ->whereKey($library->id)
                        ->wherePivot('role', LibraryRole::User->value),
                ]),
            ])
            ->get()
            ->groupBy('class_id');

        $targetClasses = $target === null
            ? collect()
            : SchoolClass::query()
                ->where('library_id', $library->id)
                ->where('school_year_id', $target->id)
                ->withCount('enrollments')
                ->orderBy('grade')
                ->orderBy('name')
                ->get();

        $sourceUserIds = $sourceEnrollments
            ->flatMap(fn (Collection $items) => $items->pluck('user_id'))
            ->unique()
            ->values();

        $targetEnrollments = $target === null || $sourceUserIds->isEmpty()
            ? collect()
            : ClassEnrollment::query()
                ->where('library_id', $library->id)
                ->where('school_year_id', $target->id)
                ->whereIn('user_id', $sourceUserIds)
                ->get(['user_id', 'class_id'])
                ->keyBy('user_id');

        return [
            'target_year' => $target,
            'source_classes' => $sourceClasses,
            'target_classes' => $targetClasses,
            'source_enrollments' => $sourceEnrollments,
            'target_enrollments' => $targetEnrollments,
        ];
    }

    /**
     * @param  ClassTransferPreviewSnapshot  $snapshot
     * @return list<ClassTransferPreviewRow>
     */
    private function buildPreviewRows(array $snapshot): array
    {
        $rows = [];

        foreach ($snapshot['source_classes'] as $class) {
            $rows[] = $this->buildPreviewRow(
                $class,
                $snapshot['source_enrollments']->get($class->id, collect()),
                $snapshot['target_year'],
                $snapshot['target_classes'],
                $snapshot['target_enrollments'],
            );
        }

        return $rows;
    }

    /**
     * @param  Collection<int, ClassEnrollment>  $classEnrollments
     * @param  Collection<int, SchoolClass>  $targetClasses
     * @param  Collection<int, ClassEnrollment>  $targetEnrollments
     * @return ClassTransferPreviewRow
     */
    private function buildPreviewRow(
        SchoolClass $class,
        Collection $classEnrollments,
        ?SchoolYear $target,
        Collection $targetClasses,
        Collection $targetEnrollments,
    ): array {
        $grade = (int) ($class->grade?->value ?? 0);
        $studentsCount = $classEnrollments->count();
        $eligibleCount = $classEnrollments
            ->filter(fn (ClassEnrollment $enrollment): bool => $this->isEligibleEnrollment($enrollment))
            ->count();
        $alreadyEnrolledCount = $classEnrollments
            ->filter(fn (ClassEnrollment $enrollment): bool => $targetEnrollments->has($enrollment->user_id))
            ->count();

        $options = $this->previewTargetOptions($target, $grade, $targetClasses);
        $suggested = $this->suggestTarget($class, $options);
        $canCreateTarget = $target !== null
            && $grade > 0
            && $grade < SchoolGrade::Eighth->value
            && $eligibleCount > 0
            && $options->isEmpty();
        $proposedName = $canCreateTarget
            ? $this->proposedTargetName($class, $grade + 1, $targetClasses->pluck('name'))
            : null;
        $status = $this->previewRowStatus(
            $grade,
            $studentsCount,
            $eligibleCount,
            $target,
            $options,
            $suggested,
        );

        return [
            'id' => $class->id,
            'name' => $class->name,
            'grade' => $class->grade?->value,
            'grade_label' => $class->grade?->getLabel(),
            'grade_full_label' => $class->grade?->getFullLabel(),
            'teacher_name' => $class->teacher_name,
            'is_active' => $class->is_active,
            'students_count' => $studentsCount,
            'eligible_students_count' => $eligibleCount,
            'skipped_students_count' => $studentsCount - $eligibleCount,
            'already_enrolled_count' => $alreadyEnrolledCount,
            'status' => $status->value,
            'suggested_target_class_id' => $suggested?->id,
            'proposed_target_class_name' => $proposedName,
            'can_create_target' => $canCreateTarget,
            'target_options' => $this->mapPreviewTargetOptions($options),
        ];
    }

    /**
     * @param  Collection<int, SchoolClass>  $targetClasses
     * @return Collection<int, SchoolClass>
     */
    private function previewTargetOptions(?SchoolYear $target, int $grade, Collection $targetClasses): Collection
    {
        if ($target === null || $grade >= SchoolGrade::Eighth->value) {
            return collect();
        }

        return $targetClasses
            ->filter(fn (SchoolClass $targetClass): bool => (int) $targetClass->grade?->value === $grade + 1
                && $targetClass->is_active)
            ->values();
    }

    private function previewRowStatus(
        int $grade,
        int $studentsCount,
        int $eligibleCount,
        ?SchoolYear $target,
        Collection $options,
        ?SchoolClass $suggested,
    ): ClassTransferRowStatusEnum {
        return match (true) {
            $grade >= SchoolGrade::Eighth->value => ClassTransferRowStatusEnum::Graduating,
            $studentsCount === 0 => ClassTransferRowStatusEnum::Empty,
            $eligibleCount === 0 => ClassTransferRowStatusEnum::Ineligible,
            $target === null => ClassTransferRowStatusEnum::NeedsTarget,
            $options->isEmpty() => ClassTransferRowStatusEnum::NeedsTarget,
            $suggested === null => ClassTransferRowStatusEnum::NeedsSelection,
            default => ClassTransferRowStatusEnum::Ready,
        };
    }

    /**
     * @param  Collection<int, SchoolClass>  $options
     * @return list<ClassTransferPreviewTargetOption>
     */
    private function mapPreviewTargetOptions(Collection $options): array
    {
        return $options
            ->map(fn (SchoolClass $targetClass): array => [
                'id' => $targetClass->id,
                'name' => $targetClass->name,
                'grade' => $targetClass->grade?->value,
                'grade_label' => $targetClass->grade?->getLabel(),
                'grade_full_label' => $targetClass->grade?->getFullLabel(),
                'students_count' => (int) ($targetClass->enrollments_count ?? 0),
                'is_active' => $targetClass->is_active,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  list<ClassTransferPreviewRow>  $rows
     * @return ClassTransferPreviewSummary
     */
    private function buildPreviewSummary(array $rows): array
    {
        $summary = [
            'classes_count' => 0,
            'students_count' => 0,
            'eligible_students_count' => 0,
            'already_enrolled_count' => 0,
            'graduating_students_count' => 0,
            'needs_target_count' => 0,
        ];

        foreach ($rows as $row) {
            $summary['classes_count']++;
            $summary['students_count'] += $row['students_count'];
            $summary['already_enrolled_count'] += $row['already_enrolled_count'];

            if ($row['status'] === ClassTransferRowStatusEnum::Graduating->value) {
                $summary['graduating_students_count'] += $row['students_count'];
            } else {
                $summary['eligible_students_count'] += $row['eligible_students_count'];
            }

            if ($row['status'] === ClassTransferRowStatusEnum::NeedsTarget->value) {
                $summary['needs_target_count']++;
            }
        }

        return $summary;
    }

    /**
     * @param  array<int, array{source_class_id?: int|string, target_class_id?: int|string|null}>  $mappings
     */
    public function transfer(
        Library $library,
        SchoolYear $source,
        SchoolYear $target,
        array $mappings,
        bool $createMissingTargetClasses,
        User $actor,
    ): ClassTransferResultDTO {
        $this->assertTargetYearNotBeforeActive($target);

        if ((int) $target->year !== (int) $source->year + 1) {
            throw ValidationException::withMessages([
                'target_school_year_id' => __('validation.custom.class_transfer_target_not_next'),
            ]);
        }

        $normalizedMappings = $this->normalizeMappings($mappings);

        return DB::transaction(fn (): ClassTransferResultDTO => $this->transferWithinTransaction(
            $library,
            $source,
            $target,
            $normalizedMappings,
            $createMissingTargetClasses,
            $actor,
        ));
    }

    private function assertSourceYearWithinActiveTransferWindow(SchoolYear $source): void
    {
        if (! $this->yearsService->isWithinActiveYearWindow($source, 1)) {
            throw ValidationException::withMessages([
                'source_school_year_id' => __('validation.custom.class_transfer_source_before_active_window'),
            ]);
        }
    }

    private function assertTargetYearNotBeforeActive(SchoolYear $target): void
    {
        if (! $this->yearsService->isWithinActiveYearWindow($target)) {
            throw ValidationException::withMessages([
                'target_school_year_id' => __('validation.custom.class_transfer_target_before_active'),
            ]);
        }
    }

    /**
     * @param  array<int, int|null>  $normalizedMappings
     */
    private function transferWithinTransaction(
        Library $library,
        SchoolYear $source,
        SchoolYear $target,
        array $normalizedMappings,
        bool $createMissingTargetClasses,
        User $actor,
    ): ClassTransferResultDTO {
        $snapshot = $this->loadTransferSnapshot($library, $source, $target, $normalizedMappings);
        $createdTargetClassIds = [];
        $createdClasses = [];
        $classResults = [];
        $graduatingStudents = 0;

        foreach ($snapshot['source_classes'] as $sourceClass) {
            $outcome = $this->processSourceClass(
                $sourceClass,
                $snapshot,
                $normalizedMappings,
                $library,
                $target,
                $createMissingTargetClasses,
                $createdTargetClassIds,
                $actor,
            );

            if ($outcome['row'] !== null) {
                $classResults[] = $outcome['row'];
            }

            if ($outcome['created_class'] !== null) {
                $createdClasses[] = $outcome['created_class'];
            }

            if ($outcome['created_target_class'] !== null) {
                $snapshot['target_classes']->put(
                    $outcome['created_target_class']->id,
                    $outcome['created_target_class'],
                );
                $createdTargetClassIds[] = $outcome['created_target_class']->id;
            }

            $graduatingStudents += $outcome['graduating_students'];
        }

        $totals = $this->buildTransferTotals(
            $classResults,
            $graduatingStudents,
            count($createdClasses),
        );

        return new ClassTransferResultDTO(
            sourceYear: $this->yearPayload($source),
            targetYear: $this->yearPayload($target),
            classes: $classResults,
            createdClasses: $createdClasses,
            totals: $totals,
        );
    }

    /**
     * @param  array<int, int|null>  $normalizedMappings
     * @return ClassTransferSnapshot
     */
    private function loadTransferSnapshot(
        Library $library,
        SchoolYear $source,
        SchoolYear $target,
        array $normalizedMappings,
    ): array {
        $sourceClasses = $this->loadSourceClasses($library, $source);
        $this->validateSourceMappings($sourceClasses, $normalizedMappings);

        $targetClasses = SchoolClass::query()
            ->where('library_id', $library->id)
            ->where('school_year_id', $target->id)
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        $sourceEnrollments = $this->loadSourceEnrollments($sourceClasses, $library);
        $targetEnrollments = $this->loadTargetEnrollments(
            $library,
            $target,
            $targetClasses,
            $sourceEnrollments,
        );

        return [
            'source_classes' => $sourceClasses,
            'target_classes' => $targetClasses,
            'source_enrollments' => $sourceEnrollments,
            'target_enrollments' => $targetEnrollments,
        ];
    }

    /**
     * @return Collection<int, SchoolClass>
     */
    private function loadSourceClasses(Library $library, SchoolYear $source): Collection
    {
        $sourceClasses = SchoolClass::query()
            ->where('library_id', $library->id)
            ->where('school_year_id', $source->id)
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        if ($sourceClasses->isEmpty()) {
            throw ValidationException::withMessages([
                'source_school_year_id' => __('validation.custom.class_transfer_source_empty'),
            ]);
        }

        return $sourceClasses;
    }

    /**
     * @param  Collection<int, SchoolClass>  $sourceClasses
     * @param  array<int, int|null>  $normalizedMappings
     */
    private function validateSourceMappings(Collection $sourceClasses, array $normalizedMappings): void
    {
        foreach (array_keys($normalizedMappings) as $sourceClassId) {
            if (! $sourceClasses->has($sourceClassId)) {
                throw ValidationException::withMessages([
                    'mappings' => __('validation.custom.class_transfer_source_class_invalid'),
                ]);
            }
        }
    }

    /**
     * @param  Collection<int, SchoolClass>  $sourceClasses
     * @return Collection<int, Collection<int, ClassEnrollment>>
     */
    private function loadSourceEnrollments(Collection $sourceClasses, Library $library): Collection
    {
        return ClassEnrollment::query()
            ->whereIn('class_id', $sourceClasses->keys())
            ->with([
                'user' => fn ($query) => $query->with([
                    'libraries' => fn ($libraries) => $libraries
                        ->whereKey($library->id)
                        ->wherePivot('role', LibraryRole::User->value),
                ]),
            ])
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->groupBy('class_id');
    }

    /**
     * @param  Collection<int, SchoolClass>  $targetClasses
     * @param  Collection<int, Collection<int, ClassEnrollment>>  $sourceEnrollments
     * @return Collection<int, ClassEnrollment>
     */
    private function loadTargetEnrollments(
        Library $library,
        SchoolYear $target,
        Collection $targetClasses,
        Collection $sourceEnrollments,
    ): Collection {
        $sourceUserIds = $sourceEnrollments
            ->flatMap(fn (Collection $items) => $items->pluck('user_id'))
            ->unique()
            ->values();

        if ($targetClasses->isEmpty() || $sourceUserIds->isEmpty()) {
            return collect();
        }

        return ClassEnrollment::query()
            ->where('library_id', $library->id)
            ->where('school_year_id', $target->id)
            ->whereIn('user_id', $sourceUserIds)
            ->lockForUpdate()
            ->get()
            ->keyBy('user_id');
    }

    /**
     * @param  ClassTransferSnapshot  $snapshot
     * @param  array<int, int|null>  $normalizedMappings
     * @param  list<int>  $createdTargetClassIds
     * @return ClassTransferClassOutcome
     */
    private function processSourceClass(
        SchoolClass $sourceClass,
        array $snapshot,
        array $normalizedMappings,
        Library $library,
        SchoolYear $target,
        bool $createMissingTargetClasses,
        array $createdTargetClassIds,
        User $actor,
    ): array {
        $outcome = [
            'row' => null,
            'created_class' => null,
            'created_target_class' => null,
            'graduating_students' => 0,
        ];

        $classEnrollments = $snapshot['source_enrollments']->get($sourceClass->id, collect());
        $grade = (int) ($sourceClass->grade?->value ?? 0);
        $studentsCount = $classEnrollments->count();
        $eligibleEnrollments = $classEnrollments
            ->filter(fn (ClassEnrollment $enrollment): bool => $this->isEligibleEnrollment($enrollment));
        $eligibleCount = $eligibleEnrollments->count();
        $selected = array_key_exists($sourceClass->id, $normalizedMappings);

        if ($grade >= SchoolGrade::Eighth->value) {
            if ($selected) {
                throw ValidationException::withMessages([
                    'mappings' => __('validation.custom.class_transfer_grade_eight'),
                ]);
            }

            $outcome['graduating_students'] = $studentsCount;

            return $outcome;
        }

        // Odeljenja koja korisnik nije oznacio za prebacivanje se
        // preskacu u potpunosti, bez validacije ciljnog odeljenja.
        if (! $selected) {
            return $outcome;
        }

        if ($studentsCount === 0) {
            $outcome['row'] = $this->classResultRow(
                $sourceClass,
                null,
                $studentsCount,
                $eligibleCount,
                0,
                0,
                0,
                0,
                0,
                false,
            );

            return $outcome;
        }

        if ($eligibleCount === 0) {
            $outcome['row'] = $this->classResultRow(
                $sourceClass,
                null,
                $studentsCount,
                $eligibleCount,
                0,
                0,
                0,
                $studentsCount,
                0,
                false,
            );

            return $outcome;
        }

        $resolvedTarget = $this->resolveTransferTarget(
            $sourceClass,
            $grade,
            $normalizedMappings[$sourceClass->id] ?? null,
            $snapshot['target_classes'],
            $createdTargetClassIds,
            $library,
            $target,
            $createMissingTargetClasses,
        );
        $targetClass = $resolvedTarget['target_class'];

        if ($resolvedTarget['created']) {
            $outcome['created_target_class'] = $targetClass;
            $outcome['created_class'] = [
                'id' => $targetClass->id,
                'name' => $targetClass->name,
                'grade' => $targetClass->grade?->value,
                'grade_label' => $targetClass->grade?->getLabel(),
                'grade_full_label' => $targetClass->grade?->getFullLabel(),
            ];
        }

        $classification = $this->classifyTargetEnrollments(
            $eligibleEnrollments,
            $snapshot['target_enrollments'],
            $targetClass,
        );

        $assignment = $this->assignUnenrolledStudents(
            $sourceClass,
            $targetClass,
            $classification,
            $actor,
        );

        $skippedIneligible = $studentsCount - $eligibleCount;
        $skippedConflict = $classification['skipped_conflict'];

        $outcome['row'] = $this->classResultRow(
            $sourceClass,
            $targetClass,
            $studentsCount,
            $eligibleCount,
            $assignment['transferred'],
            $assignment['moved'],
            $assignment['already_enrolled'],
            $skippedIneligible,
            $skippedConflict,
            $resolvedTarget['created'],
        );

        return $outcome;
    }

    /**
     * @param  ClassifiedTransferEnrollments  $classification
     * @return ClassTransferAssignmentOutcome
     */
    private function assignUnenrolledStudents(
        SchoolClass $sourceClass,
        SchoolClass $targetClass,
        array $classification,
        User $actor,
    ): array {
        $outcome = [
            'transferred' => 0,
            'moved' => 0,
            'already_enrolled' => $classification['already_enrolled'],
        ];

        if ($classification['to_assign'] === []) {
            return $outcome;
        }

        $assignmentResult = $this->classesService
            ->transferStudentsToClass(
                $sourceClass,
                $targetClass,
                $classification['to_assign'],
                $actor,
            )
            ->toArray();

        $outcome['transferred'] = (int) $assignmentResult['assigned'];
        $outcome['moved'] = (int) $assignmentResult['moved'];
        $outcome['already_enrolled'] += (int) $assignmentResult['already_assigned'];

        return $outcome;
    }

    /**
     * @param  Collection<int, SchoolClass>  $targetClasses
     * @param  list<int>  $createdTargetClassIds
     * @return ResolvedTransferTarget
     */
    private function resolveTransferTarget(
        SchoolClass $sourceClass,
        int $sourceGrade,
        ?int $targetClassId,
        Collection $targetClasses,
        array $createdTargetClassIds,
        Library $library,
        SchoolYear $target,
        bool $createMissingTargetClasses,
    ): array {
        $targetClass = null;
        $created = false;

        if ($targetClassId === null) {
            $activeOptions = $targetClasses
                ->filter(fn (SchoolClass $candidate): bool => (int) $candidate->grade?->value === $sourceGrade + 1
                    && $candidate->is_active
                    && ! in_array($candidate->id, $createdTargetClassIds, true));

            if ($activeOptions->isNotEmpty() || ! $createMissingTargetClasses) {
                throw ValidationException::withMessages([
                    'mappings' => __('validation.custom.class_transfer_target_required'),
                ]);
            }

            $targetClass = $this->createTargetClass(
                $library,
                $target,
                $sourceClass,
                $sourceGrade + 1,
                $targetClasses,
            );
            $created = true;
        } else {
            $targetClass = $targetClasses->get($targetClassId);
        }

        if (! $targetClass instanceof SchoolClass) {
            throw ValidationException::withMessages([
                'mappings' => __('validation.custom.class_transfer_target_class_invalid'),
            ]);
        }

        $this->validateTargetClass($sourceClass, $targetClass, $sourceGrade);

        return [
            'target_class' => $targetClass,
            'created' => $created,
        ];
    }

    /**
     * @param  Collection<int, ClassEnrollment>  $eligibleEnrollments
     * @param  Collection<int, ClassEnrollment>  $targetEnrollments
     * @return ClassifiedTransferEnrollments
     */
    private function classifyTargetEnrollments(
        Collection $eligibleEnrollments,
        Collection $targetEnrollments,
        SchoolClass $targetClass,
    ): array {
        $toAssign = [];
        $alreadyEnrolled = 0;
        $skippedConflict = 0;

        foreach ($eligibleEnrollments as $enrollment) {
            $targetEnrollment = $targetEnrollments->get($enrollment->user_id);

            if ($targetEnrollment === null) {
                $toAssign[] = $enrollment->user_id;

                continue;
            }

            if ((int) $targetEnrollment->class_id === (int) $targetClass->id) {
                $alreadyEnrolled++;
            } else {
                $skippedConflict++;
            }
        }

        return [
            'to_assign' => $toAssign,
            'already_enrolled' => $alreadyEnrolled,
            'skipped_conflict' => $skippedConflict,
        ];
    }

    /**
     * @param  list<ClassTransferResultRow>  $classResults
     * @return ClassTransferTotals
     */
    private function buildTransferTotals(
        array $classResults,
        int $graduatingStudents,
        int $createdTargetClasses,
    ): array {
        $totals = [
            'classes' => count($classResults),
            'students' => 0,
            'eligible_students' => 0,
            'transferred' => 0,
            'moved' => 0,
            'already_enrolled' => 0,
            'skipped' => 0,
            'skipped_ineligible' => 0,
            'skipped_conflict' => 0,
            'graduating' => $graduatingStudents,
            'created_target_classes' => $createdTargetClasses,
        ];

        foreach ($classResults as $classResult) {
            $totals['students'] += $classResult['students_total'];
            $totals['eligible_students'] += $classResult['eligible_total'];
            $totals['transferred'] += $classResult['transferred'];
            $totals['moved'] += $classResult['moved'];
            $totals['already_enrolled'] += $classResult['already_enrolled'];
            $totals['skipped_ineligible'] += $classResult['skipped_ineligible'];
            $totals['skipped_conflict'] += $classResult['skipped_conflict'];
            $totals['skipped'] += $classResult['skipped_ineligible'] + $classResult['skipped_conflict'];
        }

        return $totals;
    }

    /**
     * @param  array<int, array{source_class_id?: int|string, target_class_id?: int|string|null}>  $mappings
     * @return array<int, int|null>
     */
    private function normalizeMappings(array $mappings): array
    {
        $normalized = [];

        foreach ($mappings as $mapping) {
            $sourceClassId = (int) ($mapping['source_class_id'] ?? 0);

            if ($sourceClassId <= 0 || array_key_exists($sourceClassId, $normalized)) {
                throw ValidationException::withMessages([
                    'mappings' => __('validation.custom.class_transfer_source_class_invalid'),
                ]);
            }

            $normalized[$sourceClassId] = isset($mapping['target_class_id']) && $mapping['target_class_id'] !== null
                ? (int) $mapping['target_class_id']
                : null;
        }

        return $normalized;
    }

    private function validateTargetClass(SchoolClass $sourceClass, SchoolClass $targetClass, int $sourceGrade): void
    {
        if ((int) $targetClass->library_id !== (int) $sourceClass->library_id) {
            throw ValidationException::withMessages([
                'mappings' => __('validation.custom.class_transfer_same_library'),
            ]);
        }

        if ((int) $targetClass->school_year_id === (int) $sourceClass->school_year_id) {
            throw ValidationException::withMessages([
                'mappings' => __('validation.custom.class_transfer_target_year'),
            ]);
        }

        if ((int) $targetClass->grade?->value !== $sourceGrade + 1) {
            throw ValidationException::withMessages([
                'mappings' => __('validation.custom.class_transfer_target_grade'),
            ]);
        }

        if (! $targetClass->is_active) {
            throw ValidationException::withMessages([
                'mappings' => __('validation.custom.class_transfer_target_inactive'),
            ]);
        }
    }

    /**
     * @param  Collection<int, SchoolClass>  $targetClasses
     */
    private function createTargetClass(
        Library $library,
        SchoolYear $targetYear,
        SchoolClass $sourceClass,
        int $targetGrade,
        Collection $targetClasses,
    ): SchoolClass {
        $name = $this->proposedTargetName(
            $sourceClass,
            $targetGrade,
            $targetClasses->pluck('name'),
        );

        return SchoolClass::query()->create([
            'library_id' => $library->id,
            'school_year_id' => $targetYear->id,
            'grade' => SchoolGrade::from($targetGrade),
            'name' => $name,
            'teacher_name' => null,
            'is_active' => true,
        ]);
    }

    /**
     * @param  Collection<int, SchoolClass>  $options
     */
    private function suggestTarget(SchoolClass $sourceClass, Collection $options): ?SchoolClass
    {
        if ($options->isEmpty()) {
            return null;
        }

        if ($options->count() === 1) {
            return $options->first();
        }

        $sourceGrade = (int) ($sourceClass->grade?->value ?? 0);
        $sourceSuffix = $this->classSuffix($sourceClass->name, $sourceGrade);

        $matches = $options
            ->filter(fn (SchoolClass $option): bool => $this->classSuffix(
                $option->name,
                (int) ($option->grade?->value ?? 0),
            ) === $sourceSuffix)
            ->values();

        return $matches->count() === 1 ? $matches->first() : null;
    }

    /**
     * @param  Collection<int, string>  $existingNames
     */
    private function proposedTargetName(
        SchoolClass $sourceClass,
        int $targetGrade,
        Collection $existingNames,
    ): string {
        $sourceGrade = (int) ($sourceClass->grade?->value ?? 0);
        $suffix = $this->classSuffix($sourceClass->name, $sourceGrade);
        $base = trim((string) preg_replace(
            '/\s+/u',
            ' ',
            $targetGrade.'-'.($suffix !== '' ? $suffix : $sourceClass->name),
        ));

        $name = $base;
        $counter = 2;

        while ($existingNames->contains(fn (string $existing): bool => mb_strtolower($existing) === mb_strtolower($name))) {
            $name = $base.'-'.$counter;
            $counter++;
        }

        return $name;
    }

    private function classSuffix(string $name, int $grade): string
    {
        $pattern = '/^\s*'.$grade.'(?!\d)[\s\-–—\/.]*(.*)$/u';

        if (preg_match($pattern, $name, $matches) === 1) {
            return trim((string) ($matches[1] ?? ''));
        }

        return trim($name);
    }

    private function isEligibleEnrollment(ClassEnrollment $enrollment): bool
    {
        $user = $enrollment->user;

        return $user !== null
            && ! $user->trashed()
            && $user->libraries->isNotEmpty();
    }

    /**
     * @return array{id: int, year: int, name: string, is_active: bool}
     */
    private function yearPayload(SchoolYear $year): array
    {
        return [
            'id' => $year->id,
            'year' => $year->year,
            'name' => $year->name,
            'is_active' => $this->yearsService->isActive($year),
        ];
    }

    /** @return ClassTransferResultRow */
    private function classResultRow(
        SchoolClass $sourceClass,
        ?SchoolClass $targetClass,
        int $studentsTotal,
        int $eligibleTotal,
        int $transferred,
        int $moved,
        int $alreadyEnrolled,
        int $skippedIneligible,
        int $skippedConflict,
        bool $createdTargetClass,
    ): array {
        return [
            'source_class_id' => $sourceClass->id,
            'source_class_name' => $sourceClass->name,
            'source_grade_label' => $sourceClass->grade?->getLabel(),
            'target_class_id' => $targetClass?->id,
            'target_class_name' => $targetClass?->name,
            'target_grade_label' => $targetClass?->grade?->getLabel(),
            'students_total' => $studentsTotal,
            'eligible_total' => $eligibleTotal,
            'transferred' => $transferred,
            'moved' => $moved,
            'already_enrolled' => $alreadyEnrolled,
            'skipped_ineligible' => $skippedIneligible,
            'skipped_conflict' => $skippedConflict,
            'created_target_class' => $createdTargetClass,
        ];
    }
}
