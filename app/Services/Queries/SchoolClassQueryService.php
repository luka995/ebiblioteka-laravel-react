<?php

namespace App\Services\Queries;

use App\DTOs\Queries\SchoolClassStudentListQueryDTO;
use App\Models\ClassEnrollment;
use App\Models\SchoolClass;
use App\Services\Sorting\QuerySortBuilder;
use App\Services\Sorting\SortParser;
use App\Services\Sorting\SortRule;
use Illuminate\Database\Eloquent\Builder;

final class SchoolClassQueryService
{
    public function __construct(private readonly SortParser $sortParser) {}

    /**
     * @return Builder<ClassEnrollment>
     */
    public function students(SchoolClassStudentListQueryDTO $query, SchoolClass $schoolClass): Builder
    {
        $builder = ClassEnrollment::query()
            ->with(['user', 'assignedBy'])
            ->where('class_id', $schoolClass->id)
            ->where('library_id', $schoolClass->library_id)
            ->where('school_year_id', $schoolClass->school_year_id);

        $sort = $this->sortParser->parse($query->sorting->sort, $query->sorting->direction);

        return (new QuerySortBuilder)
            ->allow([
                'name' => fn (Builder $query, string $direction) => $query->orderByRaw(
                    '(select name from users where users.id = student_class.user_id) '.$direction
                ),
                'account' => fn (Builder $query, string $direction) => $query->orderByRaw(
                    '(select coalesce(users.username, users.email) from users where users.id = student_class.user_id) '.$direction
                ),
                'barcode' => fn (Builder $query, string $direction) => $query->orderByRaw(
                    '(select bar_code from users where users.id = student_class.user_id) '.$direction
                ),
                'assigned_at' => 'student_class.updated_at',
            ])
            ->defaults(new SortRule('name'))
            ->apply($builder, $sort);
    }
}
