<?php

namespace App\Http\Requests;

use App\Models\SchoolYear;
use App\Services\SchoolYearService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ClassTransferRequest extends FormRequest
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
            'target_school_year_id' => [
                'required',
                'integer',
                Rule::exists('school_years', 'id'),
            ],
            'create_missing_target_classes' => ['sometimes', 'boolean'],
            'mappings' => ['required', 'array', 'min:1'],
            'mappings.*' => ['array'],
            'mappings.*.source_class_id' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('classes', 'id'),
            ],
            'mappings.*.target_class_id' => [
                'nullable',
                'integer',
                Rule::exists('classes', 'id'),
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
                if (
                    $validator->errors()->has('source_school_year_id')
                    || $validator->errors()->has('target_school_year_id')
                ) {
                    return;
                }

                $schoolYears = app(SchoolYearService::class);
                $source = SchoolYear::query()->find($this->integer('source_school_year_id'));
                $target = SchoolYear::query()->find($this->integer('target_school_year_id'));

                if ($source !== null && ! $schoolYears->isWithinActiveYearWindow($source, 1)) {
                    $validator->errors()->add(
                        'source_school_year_id',
                        __('validation.custom.class_transfer_source_before_active_window'),
                    );
                }

                if ($target !== null && ! $schoolYears->isWithinActiveYearWindow($target)) {
                    $validator->errors()->add(
                        'target_school_year_id',
                        __('validation.custom.class_transfer_target_before_active'),
                    );
                }
            },
        ];
    }

    /**
     * @return array<int, array{source_class_id: int, target_class_id: int|null}>
     */
    public function mappings(): array
    {
        return array_values(array_map(
            fn (array $mapping): array => [
                'source_class_id' => (int) $mapping['source_class_id'],
                'target_class_id' => isset($mapping['target_class_id']) && $mapping['target_class_id'] !== null
                    ? (int) $mapping['target_class_id']
                    : null,
            ],
            (array) $this->input('mappings', []),
        ));
    }
}
