<?php

namespace App\Http\Requests;

use App\Enums\SchoolGrade;
use App\Models\SchoolClass;
use App\Services\SchoolClassService;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UpdateSchoolClassRequest extends FormRequest
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
        /** @var SchoolClass $class */
        $class = $this->route('schoolClass');
        $schoolYearId = $this->integer('school_year_id', (int) $class->school_year_id);

        return [
            'school_year_id' => [
                'sometimes',
                'bail',
                'required',
                'integer',
                Rule::exists('school_years', 'id'),
                function (string $attribute, mixed $value, Closure $fail) use ($class): void {
                    try {
                        app(SchoolClassService::class)->validateYearChange($class, (int) $value, $this->user());
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
                Rule::unique('classes', 'name')
                    ->where(fn ($query) => $query
                        ->where('library_id', $class->library_id)
                        ->where('school_year_id', $schoolYearId))
                    ->ignore($class),
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
