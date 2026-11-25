<?php

namespace App\Http\Requests;

use App\Services\ActiveLibraryService;
use App\Services\AuthorResolverService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreAuthorRequest extends FormRequest
{
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
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('authors', 'name')->where('library_id', $this->input('library_id')),
            ],
            'library_id' => $libraryIdRule,
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $libraryId = (int) $this->input('library_id');

                if (! $this->user()->isSuperAdmin() && ! $libraryId) {
                    $validator->errors()->add('library_id', __('validation.custom.active_library_required'));

                    return;
                }

                if ($validator->errors()->has('name') || $libraryId <= 0) {
                    return;
                }

                $existing = app(AuthorResolverService::class)
                    ->findInLibrary($libraryId, (string) $this->input('name'));

                if ($existing !== null) {
                    $validator->errors()->add('name', __('validation.custom.author_duplicate'));
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
            'name.unique' => __('validation.custom.author_duplicate'),
        ];
    }
}
