<?php

namespace App\Http\Requests;

use App\Models\Author;
use App\Services\ActiveLibraryService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAuthorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Bibliotečki administrator sme da menja samo autora iz svoje
     * aktivne biblioteke (tuđi -> 404).
     */
    public function prepareForValidation(): void
    {
        if (! $this->user()->isSuperAdmin()) {
            /** @var Author $author */
            $author = $this->route('author');
            $active = app(ActiveLibraryService::class)->resolve($this->user());

            abort_unless($active && $author->library_id === $active->id, 404);
        }
    }

    /**
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        /** @var Author|null $author */
        $author = $this->route('author');

        return [
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('authors', 'name')
                    ->where('library_id', $author?->library_id)
                    ->ignore($author),
            ],
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
