<?php

namespace App\Support;

use App\Models\SchoolYear;
use Closure;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Validator;

class SchoolYearValidator
{
    /**
     * @param  array<string, mixed>  $data
     * @return Closure(Validator): void
     */
    public function after(array $data, ?SchoolYear $schoolYear = null): Closure
    {
        return function (Validator $validator) use ($data, $schoolYear): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $year = (int) $data['year'];
            $startsOn = Carbon::parse((string) $data['starts_on']);
            $endsOn = Carbon::parse((string) $data['ends_on']);

            if ($startsOn->year !== $year) {
                $validator->errors()->add('starts_on', __('validation.custom.school_year_start_mismatch'));
            }

            if ($endsOn->year !== $year + 1) {
                $validator->errors()->add('ends_on', __('validation.custom.school_year_end_mismatch'));
            }

            $overlaps = SchoolYear::query()
                ->when(
                    $schoolYear !== null,
                    fn ($query) => $query->whereKeyNot($schoolYear->id),
                )
                ->where('starts_on', '<', $endsOn->toDateString())
                ->where('ends_on', '>', $startsOn->toDateString())
                ->exists();

            if ($overlaps) {
                $validator->errors()->add('starts_on', __('validation.custom.school_year_overlap'));
            }

            if ($schoolYear !== null && $year !== $schoolYear->year && $schoolYear->classes()->exists()) {
                $validator->errors()->add('year', __('validation.custom.school_year_year_locked'));
            }
        };
    }
}
