<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesCategoryParent;
use App\Services\ActiveLibraryService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreCategoryRequest extends FormRequest
{
    use ValidatesCategoryParent;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * Za bibliotečkog administratora biblioteka se preuzima iz aktivne
     * biblioteke (klijent je ne šalje).
     */
    public function prepareForValidation(): void
    {
        if (! $this->user()->isSuperAdmin()) {
            $active = app(ActiveLibraryService::class)->resolve($this->user());
            $this->merge(['library_id' => $active?->id]);
        }
    }

    /**
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        $libraryIdRule = $this->user()->isSuperAdmin()
            ? ['required', 'integer', Rule::exists('libraries', 'id')]
            : ['nullable', 'integer', Rule::exists('libraries', 'id')];

        return [
            'name' => ['required', 'string', 'max:255'],
            'library_id' => $libraryIdRule,
            'parent_id' => ['nullable', 'integer', Rule::exists('categories', 'id')],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if (! $this->user()->isSuperAdmin() && ! $this->input('library_id')) {
                    $validator->errors()->add('library_id', __('validation.custom.active_library_required'));

                    return;
                }

                $this->validateCategoryParent($validator, (int) $this->input('library_id'), null);
            },
        ];
    }
}
