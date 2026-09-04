<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreTagRequest extends FormRequest
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
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('tags', 'name')->where('library_id', $this->input('library_id')),
            ],
            'library_id' => ['required', 'integer', Rule::exists('libraries', 'id')],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($this->user()->isSuperAdmin()) {
                    return;
                }

                if (! $this->user()->managesLibrary((int) $this->input('library_id'))) {
                    $validator->errors()->add('library_id', __('validation.custom.library_not_managed'));
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.unique' => __('validation.custom.tag_duplicate'),
        ];
    }
}
