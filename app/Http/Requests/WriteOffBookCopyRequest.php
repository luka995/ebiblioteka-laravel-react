<?php

namespace App\Http\Requests;

use App\Enums\BookCopyWriteOffReason;
use App\Models\BookCopy;
use App\Services\ActiveLibraryService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class WriteOffBookCopyRequest extends FormRequest
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
            'reason' => ['required', Rule::enum(BookCopyWriteOffReason::class)],
            'occurred_at' => ['nullable', 'date'],
            'notice' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
