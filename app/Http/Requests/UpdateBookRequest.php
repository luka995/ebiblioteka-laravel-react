<?php

namespace App\Http\Requests;

use App\Models\Book;
use App\Services\ActiveLibraryService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Bibliotecko osoblje sme da menja samo naslov iz svoje aktivne
     * biblioteke (tudji -> 404).
     */
    public function prepareForValidation(): void
    {
        if (! $this->user()->isSuperAdmin()) {
            /** @var Book $book */
            $book = $this->route('book');
            $active = app(ActiveLibraryService::class)->resolve($this->user());

            abort_unless($active && $book->library_id === $active->id, 404);
        }
    }

    /**
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        /** @var Book $book */
        $book = $this->route('book');
        $libraryId = $book->library_id;

        return [
            'name' => ['required', 'string', 'max:255'],
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
        ];
    }
}
