<?php

namespace App\Http\Requests;

use App\Enums\BarcodePrintFormat;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PrintBookCopiesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
            'format' => ['required', Rule::enum(BarcodePrintFormat::class)],
        ];
    }

    /**
     * @return array<int, int>
     */
    public function copyIds(): array
    {
        return array_values(array_unique(array_map('intval', $this->validated('ids'))));
    }
}
