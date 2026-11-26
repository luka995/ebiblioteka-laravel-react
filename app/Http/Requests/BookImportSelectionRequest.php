<?php

namespace App\Http\Requests;

use App\Enums\BookImportFailureEnum;
use App\Enums\BookImportModeEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

abstract class BookImportSelectionRequest extends FormRequest
{
    abstract protected function selectableStatus(): string;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'mode' => ['required', Rule::enum(BookImportModeEnum::class)],
            'row_ids' => ['required_without:all_matching', 'array', 'list', 'min:1', 'max:'.config('book_imports.max_rows')],
            'row_ids.*' => ['required', 'integer', 'min:1', 'distinct'],
            'all_matching' => ['sometimes', 'accepted'],
            'filters' => ['required_if:all_matching,true', 'prohibited_unless:all_matching,true', 'array:status,failure_code,attempt_mode,search'],
            'filters.status' => ['sometimes', 'nullable', Rule::in([$this->selectableStatus()])],
            'filters.failure_code' => ['sometimes', 'nullable', Rule::enum(BookImportFailureEnum::class)],
            'filters.attempt_mode' => ['sometimes', 'nullable', Rule::enum(BookImportModeEnum::class)],
            'filters.search' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $data = $this->all();
            $unknown = array_diff(array_keys($data), ['mode', 'row_ids', 'all_matching', 'filters']);

            if ($unknown !== []) {
                $validator->errors()->add('request', __('book_imports.errors.unsupported_parameters'));
            }

            $hasRows = array_key_exists('row_ids', $data);
            $hasMatching = array_key_exists('all_matching', $data);

            if ($hasRows === $hasMatching) {
                $validator->errors()->add('row_ids', __('book_imports.errors.invalid_selection'));
            }
        }];
    }
}
