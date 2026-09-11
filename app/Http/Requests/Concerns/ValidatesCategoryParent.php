<?php

namespace App\Http\Requests\Concerns;

use App\Models\Category;
use Illuminate\Validation\Validator;

trait ValidatesCategoryParent
{
    /**
     * Proverava da li izabrana roditeljska kategorija pripada istoj biblioteci
     * i da ne uvodi ciklus (self ili potomak).
     */
    protected function validateCategoryParent(Validator $validator, int $libraryId, ?int $selfId): void
    {
        if (! $this->filled('parent_id')) {
            return;
        }

        $parent = Category::query()->find((int) $this->input('parent_id'));

        if (! $parent) {
            return;
        }

        if ($parent->library_id !== $libraryId) {
            $validator->errors()->add('parent_id', __('validation.custom.category_parent_invalid'));
        }

        if ($selfId !== null && ($parent->id === $selfId || $parent->hasAncestor($selfId))) {
            $validator->errors()->add('parent_id', __('validation.custom.category_parent_invalid'));
        }
    }
}
