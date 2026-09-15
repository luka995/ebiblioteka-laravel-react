<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesBookCopyMetadata;
use App\Models\Book;
use App\Services\ActiveLibraryService;
use Illuminate\Foundation\Http\FormRequest;

class StoreBookCopiesRequest extends FormRequest
{
    use ValidatesBookCopyMetadata;

    public function authorize(): bool
    {
        return true;
    }

    public function prepareForValidation(): void
    {
        if ($this->user()->isSuperAdmin()) {
            return;
        }

        /** @var Book $book */
        $book = $this->route('book');
        $active = app(ActiveLibraryService::class)->resolve($this->user());

        abort_unless($active && $book->library_id === $active->id, 404);
    }

    /**
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        /** @var Book $book */
        $book = $this->route('book');
        $auto = $book->library->inv_number_auto;

        return array_merge([
            'copies' => $auto ? ['required', 'integer', 'min:1', 'max:100'] : ['nullable', 'integer'],
            'order_number' => $auto
                ? ['nullable', 'string']
                : ['required', 'string', 'regex:/^\d+$/', 'max:32'],
        ], $this->bookCopyMetadataRules());
    }
}
