<?php

namespace App\Http\Requests;

use App\Support\SchoolYearValidator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSchoolYearRequest extends FormRequest
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
        return [
            'year' => [
                'required',
                'integer',
                'min:2000',
                'max:2100',
                Rule::unique('school_years', 'year'),
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
        return [
            app(SchoolYearValidator::class)->after($this->all()),
        ];
    }
}
