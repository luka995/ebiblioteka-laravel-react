<?php

namespace App\Http\Requests;

use App\Enums\SchoolGrade;
use App\Services\ActiveLibraryService;
use App\Services\SchoolClassService;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class StoreSchoolClassRequest extends FormRequest
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
        $libraryId = app(ActiveLibraryService::class)
            ->resolve($this->user())
            ?->id;
        $schoolYearId = $this->input('school_year_id');

        return [
            'school_year_id' => [
                'bail',
                'required',
                'integer',
                Rule::exists('school_years', 'id'),
                function (string $attribute, mixed $value, Closure $fail): void {
                    try {
                        app(SchoolClassService::class)->validateCreationYear((int) $value);
                    } catch (ValidationException $exception) {
                        $fail($exception->errors()['school_year_id'][0]);
                    }
                },
            ],
            'grade' => ['required', 'integer', Rule::enum(SchoolGrade::class)],
            'name' => [
                'required',
                'string',
                'max:50',
                Rule::unique('classes', 'name')->where(
                    fn ($query) => $query
                        ->where('library_id', $libraryId)
                        ->where('school_year_id', $schoolYearId)
                ),
            ],
            'teacher_name' => ['nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.unique' => __('validation.custom.class_duplicate'),
            'grade.enum' => __('validation.custom.class_grade_invalid'),
        ];
    }
}
