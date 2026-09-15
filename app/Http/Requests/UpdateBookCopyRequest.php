<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesBookCopyMetadata;
use App\Models\BookCopy;
use App\Services\ActiveLibraryService;
use Illuminate\Foundation\Http\FormRequest;

class UpdateBookCopyRequest extends FormRequest
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

        /** @var BookCopy $copy */
        $copy = $this->route('bookCopy');
        $active = app(ActiveLibraryService::class)->resolve($this->user());

        abort_unless($active && $copy->library_id === $active->id, 404);
    }

    /**
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        return array_merge([
            'order_number' => ['nullable', 'string', 'regex:/^\d+$/', 'max:32'],
        ], $this->bookCopyMetadataRules());
    }
}
