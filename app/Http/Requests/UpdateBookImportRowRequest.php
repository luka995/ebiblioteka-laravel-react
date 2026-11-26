<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateBookImportRowRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'input_version' => ['required', 'integer', 'min:1'],
            'changes' => ['required', 'array', 'min:1'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $unknown = array_diff(array_keys($this->all()), ['input_version', 'changes']);

            if ($unknown !== []) {
                $validator->errors()->add('request', __('book_imports.errors.unsupported_parameters'));
            }
        }];
    }
}
