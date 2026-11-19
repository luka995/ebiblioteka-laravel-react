<?php

namespace App\Http\Requests;

use App\Models\SchoolYear;
use App\Support\SchoolYearValidator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSchoolYearRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        /** @var SchoolYear $schoolYear */
        $schoolYear = $this->route('schoolYear');

        return [
            'year' => [
                'required',
                'integer',
                'min:2000',
                'max:2100',
                Rule::unique('school_years', 'year')->ignore($schoolYear),
            ],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after:starts_on'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        /** @var SchoolYear $schoolYear */
        $schoolYear = $this->route('schoolYear');

        return [
            app(SchoolYearValidator::class)->after($this->all(), $schoolYear),
        ];
    }
}
