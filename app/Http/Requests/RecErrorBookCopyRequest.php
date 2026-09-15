<?php

namespace App\Http\Requests;

use App\Models\BookCopy;
use App\Services\ActiveLibraryService;
use Illuminate\Foundation\Http\FormRequest;

class RecErrorBookCopyRequest extends FormRequest
{
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
        return [
            'rec_error' => ['required', 'boolean'],
            'rec_error_notice' => ['nullable', 'string', 'max:255', 'required_if:rec_error,true'],
        ];
    }
}
