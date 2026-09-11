<?php

namespace App\Http\Requests;

use App\Enums\BarcodePrintFormat;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PrintUserBulkBarcodeRequest extends FormRequest
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
            'user_ids' => ['required', 'array', 'min:1'],
            'user_ids.*' => ['integer'],
            'format' => ['required', Rule::enum(BarcodePrintFormat::class)],
        ];
    }

    /**
     * @return array<int, int>
     */
    public function userIds(): array
    {
        return array_values(array_unique(array_map('intval', $this->validated('user_ids'))));
    }
}
