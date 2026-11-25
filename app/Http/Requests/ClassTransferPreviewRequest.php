<?php

namespace App\Http\Requests;

use App\Models\SchoolYear;
use App\Services\SchoolYearService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ClassTransferPreviewRequest extends FormRequest
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
            'source_school_year_id' => [
                'required',
                'integer',
                Rule::exists('school_years', 'id'),
            ],
        ];
    }

    /**
     * @return array<int, \Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->has('source_school_year_id')) {
                    return;
                }

                $schoolYears = app(SchoolYearService::class);
                $source = SchoolYear::query()->find($this->integer('source_school_year_id'));

                if (
                    $source !== null
                    && ! $schoolYears->isWithinActiveYearWindow($source, 1)
                ) {
                    $validator->errors()->add(
                        'source_school_year_id',
                        __('validation.custom.class_transfer_source_before_active_window'),
                    );
                }
            },
        ];
    }
}
