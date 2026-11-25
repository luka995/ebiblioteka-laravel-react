<?php

namespace App\Services;

use App\DTOs\StudentAssignmentResultDTO;
use App\Enums\LibraryRole;
use App\Enums\SchoolGrade;
use App\Enums\StudentAssignmentOutcomeEnum;
use App\Models\ClassEnrollment;
use App\Models\ClassroomHistory;
use App\Models\Library;
use App\Models\SchoolClass;
use App\Models\User;
use App\Queries\Concerns\AppliesTransliteratedSearch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SchoolClassService
{
    use AppliesTransliteratedSearch;

    public function __construct(private readonly SchoolYearService $yearsService) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, Library $library, ?User $actor = null): SchoolClass
    {
        $this->validateCreationYear((int) $data['school_year_id']);

        return SchoolClass::query()->create([
            'library_id' => $library->id,
            'school_year_id' => (int) $data['school_year_id'],
            'grade' => SchoolGrade::from((int) $data['grade']),
            'name' => $this->normalizeName((string) $data['name']),
            'teacher_name' => $data['teacher_name'] ?? null,
            'is_active' => (bool) ($data['is_active'] ?? true),
        ]);
    }

    public function validateCreationYear(int $schoolYearId): void
    {
        if ($this->yearsService->requireActive()->id !== $schoolYearId) {
            throw ValidationException::withMessages([
                'school_year_id' => __('validation.custom.class_active_school_year_required'),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(SchoolClass $class, array $data, User $actor): SchoolClass
    {
        return DB::transaction(function () use ($class, $data, $actor): SchoolClass {
            $class = SchoolClass::query()->lockForUpdate()->findOrFail($class->id);
            $schoolYearId = (int) ($data['school_year_id'] ?? $class->school_year_id);

            $this->validateYearChange($class, $schoolYearId, $actor);

            $class->update([
                'school_year_id' => $schoolYearId,
                'grade' => SchoolGrade::from((int) $data['grade']),
                'name' => $this->normalizeName((string) $data['name']),
                'teacher_name' => $data['teacher_name'] ?? null,
                'is_active' => (bool) ($data['is_active'] ?? $class->is_active),
            ]);

            return $class->refresh();
        });
    }

    public function validateYearChange(SchoolClass $class, int $schoolYearId, User $actor): void
    {
        if ($schoolYearId === (int) $class->school_year_id) {
            return;
        }

        if (! $actor->isSuperAdmin()) {
            throw ValidationException::withMessages([
                'school_year_id' => __('validation.custom.class_year_super_admin_only'),
            ]);
        }

        Validator::make(
            ['school_year_id' => $schoolYearId],
            ['school_year_id' => ['required', 'integer', Rule::exists('school_years', 'id')]],
        )->validate();

        if ($class->enrollments()->exists() || $class->history()->exists() || $class->previousHistory()->exists()) {
            throw ValidationException::withMessages([
                'school_year_id' => __('validation.custom.class_year_has_records'),
            ]);
        }
    }

    public function delete(SchoolClass $class): void
    {
        if ($class->enrollments()->exists()) {
            throw ValidationException::withMessages([
                'class' => __('validation.custom.class_has_students'),
            ]);
        }

        if ($class->history()->exists() || $class->previousHistory()->exists()) {
            throw ValidationException::withMessages([
                'class' => __('validation.custom.class_has_history'),
            ]);
        }

        $class->delete();
    }

    /**
     * @param  array<int, int>  $userIds
     */
    public function assignStudents(SchoolClass $class, array $userIds, User $actor): StudentAssignmentResultDTO
    {
        $this->ensureClassIsActive($class);

        $userIds = $this->normalizeUserIds($userIds);
        $users = $this->eligibleStudents($class, $userIds);
        $this->validateStudentCount($users, $userIds);

        return $this->assignStudentsInTransaction($class, $users, $actor);
    }

    /**
     * Prebacuje ucenike iz izvornog u ciljno odeljenje naredne skolske godine.
     *
     * @param  array<int, int>  $userIds
     */
    public function transferStudentsToClass(
        SchoolClass $sourceClass,
        SchoolClass $targetClass,
        array $userIds,
        User $actor,
    ): StudentAssignmentResultDTO {
        $this->validateTransferPair($sourceClass, $targetClass);

        $userIds = $this->normalizeUserIds($userIds);
        $users = $this->eligibleStudents($targetClass, $userIds);
        $this->validateStudentCount($users, $userIds);

        return $this->assignStudentsInTransaction($targetClass, $users, $actor, $sourceClass);
    }

    private function ensureClassIsActive(SchoolClass $class): void
    {
        if (! $class->is_active) {
            throw ValidationException::withMessages([
                'class' => __('validation.custom.class_inactive'),
            ]);
        }
    }

    private function validateTransferPair(SchoolClass $sourceClass, SchoolClass $targetClass): void
    {
        if ((int) $sourceClass->library_id !== (int) $targetClass->library_id) {
            throw ValidationException::withMessages([
                'target_class_id' => __('validation.custom.class_transfer_same_library'),
            ]);
        }

        $sourceYear = $sourceClass->schoolYear;
        $targetYear = $targetClass->schoolYear;

        if (
            $sourceYear === null
            || $targetYear === null
            || (int) $targetYear->year !== (int) $sourceYear->year + 1
        ) {
            throw ValidationException::withMessages([
                'target_class_id' => __('validation.custom.class_transfer_target_year'),
            ]);
        }

        if ((int) $targetClass->grade->value !== (int) $sourceClass->grade->value + 1) {
            throw ValidationException::withMessages([
                'target_class_id' => __('validation.custom.class_transfer_target_grade'),
            ]);
        }

        $this->ensureClassIsActive($targetClass);
    }

    /**
     * @param  array<int, int>  $userIds
     * @return array<int, int>
     */
    private function normalizeUserIds(array $userIds): array
    {
        return array_values(array_unique(array_map('intval', $userIds)));
    }

    /**
     * @param  array<int, int>  $userIds
     * @return Collection<int, User>
     */
    private function eligibleStudents(SchoolClass $class, array $userIds): Collection
    {
        return User::query()
            ->whereIn('id', $userIds)
            ->whereHas('libraries', function (Builder $query) use ($class): void {
                $query->whereKey($class->library_id)
                    ->where('library_user.role', LibraryRole::User->value);
            })
            ->get();
    }

    /**
     * @param  Collection<int, User>  $users
     * @param  array<int, int>  $userIds
     */
    private function validateStudentCount(Collection $users, array $userIds): void
    {
        if ($users->count() !== count($userIds)) {
            throw ValidationException::withMessages([
                'user_ids' => __('validation.custom.class_students_invalid'),
            ]);
        }
    }

    /**
     * @param  Collection<int, User>  $users
     */
    private function assignStudentsInTransaction(
        SchoolClass $class,
        Collection $users,
        User $actor,
        ?SchoolClass $previousSchoolClass = null,
    ): StudentAssignmentResultDTO {
        return DB::transaction(function () use ($class, $users, $actor, $previousSchoolClass): StudentAssignmentResultDTO {
            $result = new StudentAssignmentResultDTO;

            foreach ($users as $user) {
                $result->record($this->assignStudent($class, $user, $actor, $previousSchoolClass));
            }

            return $result;
        });
    }

    private function assignStudent(
        SchoolClass $class,
        User $user,
        User $actor,
        ?SchoolClass $previousSchoolClass = null,
    ): StudentAssignmentOutcomeEnum {
        $enrollment = $this->findEnrollmentForUpdate($class, $user);

        if ($enrollment !== null && (int) $enrollment->class_id === (int) $class->id) {
            return StudentAssignmentOutcomeEnum::AlreadyAssigned;
        }

        $previousClassId = $enrollment?->class_id ?? $previousSchoolClass?->id;
        $previousSchoolYearId = $enrollment === null ? $previousSchoolClass?->school_year_id : null;
        $outcome = $enrollment === null
            ? StudentAssignmentOutcomeEnum::Assigned
            : StudentAssignmentOutcomeEnum::Moved;

        if ($enrollment === null) {
            $this->createEnrollment($class, $user, $actor);
        } else {
            $this->moveEnrollment($enrollment, $class, $actor);
        }

        $this->recordAssignmentHistory($class, $user, $actor, $previousClassId, $previousSchoolYearId);

        return $outcome;
    }

    private function findEnrollmentForUpdate(SchoolClass $class, User $user): ?ClassEnrollment
    {
        return ClassEnrollment::query()
            ->where('user_id', $user->id)
            ->where('library_id', $class->library_id)
            ->where('school_year_id', $class->school_year_id)
            ->lockForUpdate()
            ->first();
    }

    private function createEnrollment(SchoolClass $class, User $user, User $actor): ClassEnrollment
    {
        return ClassEnrollment::query()->create([
            'user_id' => $user->id,
            'library_id' => $class->library_id,
            'school_year_id' => $class->school_year_id,
            'class_id' => $class->id,
            'assigned_by_id' => $actor->id,
        ]);
    }

    private function moveEnrollment(ClassEnrollment $enrollment, SchoolClass $class, User $actor): void
    {
        $enrollment->update([
            'class_id' => $class->id,
            'assigned_by_id' => $actor->id,
        ]);
    }

    private function recordAssignmentHistory(
        SchoolClass $class,
        User $user,
        User $actor,
        ?int $previousClassId,
        ?int $previousSchoolYearId = null,
    ): void {
        ClassroomHistory::query()->create([
            'user_id' => $user->id,
            'library_id' => $class->library_id,
            'school_year_id' => $class->school_year_id,
            'class_id' => $class->id,
            'previous_class_id' => $previousClassId,
            'previous_school_year_id' => $previousClassId === null
                ? null
                : ($previousSchoolYearId ?? $class->school_year_id),
            'changed_by_id' => $actor->id,
            'changed_at' => now(),
        ]);
    }

    /**
     * @param  array<int, int>  $userIds
     */
    public function unassignStudents(SchoolClass $class, array $userIds, User $actor): int
    {
        $userIds = $this->normalizeUserIds($userIds);

        return DB::transaction(function () use ($class, $userIds, $actor): int {
            $enrollments = ClassEnrollment::query()
                ->where('class_id', $class->id)
                ->whereIn('user_id', $userIds)
                ->lockForUpdate()
                ->get();

            foreach ($enrollments as $enrollment) {
                $this->deleteEnrollment($enrollment);
                $this->recordUnassignmentHistory($class, $enrollment, $actor);
            }

            return $enrollments->count();
        });
    }

    private function deleteEnrollment(ClassEnrollment $enrollment): void
    {
        $enrollment->delete();
    }

    private function recordUnassignmentHistory(SchoolClass $class, ClassEnrollment $enrollment, User $actor): void
    {
        ClassroomHistory::query()->create([
            'user_id' => $enrollment->user_id,
            'library_id' => $class->library_id,
            'school_year_id' => $class->school_year_id,
            'class_id' => null,
            'previous_class_id' => $class->id,
            'previous_school_year_id' => $class->school_year_id,
            'changed_by_id' => $actor->id,
            'changed_at' => now(),
        ]);
    }

    /**
     * @return Builder<User>
     */
    public function availableStudentsQuery(SchoolClass $class, string $search = ''): Builder
    {
        $query = User::query()
            ->whereHas('libraries', function (Builder $membership) use ($class): void {
                $membership->whereKey($class->library_id)
                    ->where('library_user.role', LibraryRole::User->value);
            })
            ->whereDoesntHave('classEnrollments', fn (Builder $enrollment) => $enrollment
                ->where('class_id', $class->id))
            ->with([
                'classEnrollments' => fn ($enrollments) => $enrollments
                    ->where('library_id', $class->library_id)
                    ->where('school_year_id', $class->school_year_id)
                    ->with('schoolClass'),
            ]);

        $search = trim($search);

        if ($search !== '') {
            $query->where(function (Builder $group) use ($search): void {
                $this->whereTransliterated($group, 'first_name', $search);
                $this->whereTransliterated($group, 'last_name', $search, 'or');
                $this->whereTransliterated($group, 'name', $search, 'or');
                $this->whereTransliterated($group, 'email', $search, 'or');
                $this->whereTransliterated($group, 'username', $search, 'or');
                $group->orWhere('bar_code', 'like', '%'.$search.'%');
            });
        }

        return $query
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->orderBy('name');
    }

    private function normalizeName(string $name): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $name));
    }
}
