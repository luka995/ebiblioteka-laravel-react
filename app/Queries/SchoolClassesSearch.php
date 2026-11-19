<?php

namespace App\Queries;

use App\Enums\SchoolGrade;
use App\Models\SchoolClass;
use App\Queries\Concerns\AppliesTransliteratedSearch;
use Illuminate\Database\Eloquent\Builder;

class SchoolClassesSearch
{
    use AppliesTransliteratedSearch;

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<SchoolClass>
     */
    public function build(int $libraryId, ?int $activeSchoolYearId, array $filters): Builder
    {
        $query = SchoolClass::query()
            ->with(['library', 'schoolYear'])
            ->withCount('enrollments')
            ->where('library_id', $libraryId);

        $schoolYearId = (int) ($filters['school_year_id'] ?? 0);

        if ($schoolYearId <= 0) {
            $schoolYearId = $activeSchoolYearId ?? 0;
        }

        if ($schoolYearId > 0) {
            $query->where('school_year_id', $schoolYearId);
        }

        $gradeValue = $filters['grade'] ?? null;

        if ($gradeValue !== null && $gradeValue !== '') {
            $grade = SchoolGrade::tryFrom((int) $gradeValue);

            if ($grade === null) {
                $query->whereRaw('1 = 0');
            } else {
                $query->where('grade', $grade->value);
            }
        }

        $term = trim((string) ($filters['search'] ?? ''));

        if ($term !== '') {
            $query->where(function (Builder $group) use ($term): void {
                $this->whereTransliterated($group, 'name', $term, 'or');
                $this->whereTransliterated($group, 'teacher_name', $term, 'or');
            });
        }

        if (array_key_exists('is_active', $filters)) {
            $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        return $query->orderByDesc('school_year_id')
            ->orderBy('grade')
            ->orderBy('name');
    }
}
