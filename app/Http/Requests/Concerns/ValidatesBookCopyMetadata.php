<?php

namespace App\Http\Requests\Concerns;

/**
 * Zajednicka pravila za bibliografska polja fizicke jedinice.
 */
trait ValidatesBookCopyMetadata
{
    /**
     * @return array<string, array<mixed>>
     */
    protected function bookCopyMetadataRules(): array
    {
        return [
            'seq_number' => ['nullable', 'integer'],
            'isbn' => ['nullable', 'string', 'max:32'],
            'publisher' => ['nullable', 'string', 'max:255'],
            'publish_place' => ['nullable', 'string', 'max:255'],
            'publish_year' => ['nullable', 'string', 'max:16'],
            'issue_number' => ['nullable', 'string', 'max:255'],
            'num_of_pages' => ['nullable', 'integer', 'min:0'],
            'dimension' => ['nullable', 'string', 'max:255'],
            'part' => ['nullable', 'string', 'max:255'],
            'udk' => ['nullable', 'string', 'max:255'],
            'binding' => ['nullable', 'string', 'max:255'],
            'origin' => ['nullable', 'string', 'max:255'],
            'book_number' => ['nullable', 'string', 'max:255'],
            'place_on_shelf' => ['nullable', 'string', 'max:255'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'date_add' => ['nullable', 'date'],
            'notice' => ['nullable', 'string'],
            'rec_error' => ['nullable', 'boolean'],
            'rec_error_notice' => ['nullable', 'string', 'max:255'],
        ];
    }
}
