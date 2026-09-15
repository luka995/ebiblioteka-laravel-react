<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesBookCopyMetadata;
use App\Models\Library;
use App\Services\ActiveLibraryService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreBookRequest extends FormRequest
{
    use ValidatesBookCopyMetadata;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * Za biblioteckog administratora biblioteka se preuzima iz aktivne
     * biblioteke (klijent je ne salje).
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
        $libraryId = $this->input('library_id');
        $auto = $libraryId ? (bool) Library::whereKey($libraryId)->value('inv_number_auto') : false;
        $withCopies = $this->boolean('with_copies');

        return array_merge([
            'name' => ['required', 'string', 'max:255'],
            'library_id' => $this->user()->isSuperAdmin()
                ? ['required', 'integer', Rule::exists('libraries', 'id')]
                : ['nullable', 'integer', Rule::exists('libraries', 'id')],
            'with_copies' => ['sometimes', 'boolean'],
            'confirm_duplicate' => ['sometimes', 'boolean'],
            'copies' => $withCopies && $auto
                ? ['required', 'integer', 'min:1', 'max:100']
                : ['nullable', 'integer'],
            'order_number' => $withCopies && ! $auto
                ? ['required', 'string', 'regex:/^\d+$/', 'max:32']
                : ['nullable', 'string'],
            'category_primary_id' => [
                'nullable', 'integer',
                Rule::exists('categories', 'id')->where('library_id', $libraryId),
            ],
            'category_secondary_id' => [
                'nullable', 'integer',
                Rule::exists('categories', 'id')->where('library_id', $libraryId),
            ],
            'description' => ['nullable', 'string'],
            'image' => ['nullable', 'string', 'max:255'],
            'cover_url' => ['nullable', 'string', 'max:2048'],
            'authors' => ['nullable', 'array'],
            'authors.*' => ['nullable', 'string', 'max:255'],
        ], $this->bookCopyMetadataRules());
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (! $this->user()->isSuperAdmin() && ! $this->input('library_id')) {
                    $validator->errors()->add('library_id', __('validation.custom.active_library_required'));
                }
            },
        ];
    }
}
