<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesCategoryParent;
use App\Models\Category;
use App\Services\ActiveLibraryService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateCategoryRequest extends FormRequest
{
    use ValidatesCategoryParent;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * Bibliotečki administrator sme da menja samo kategoriju iz svoje
     * aktivne biblioteke (tuđa -> 404).
     */
    public function prepareForValidation(): void
    {
        if (! $this->user()->isSuperAdmin()) {
            /** @var Category $category */
            $category = $this->route('category');
            $active = app(ActiveLibraryService::class)->resolve($this->user());

            abort_unless($active && $category->library_id === $active->id, 404);
        }
    }

    /**
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'parent_id' => ['nullable', 'integer', Rule::exists('categories', 'id')],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                /** @var Category $category */
                $category = $this->route('category');

                $this->validateCategoryParent($validator, (int) $category->library_id, (int) $category->id);
            },
        ];
    }
}
