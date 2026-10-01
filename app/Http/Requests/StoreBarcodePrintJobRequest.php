<?php

namespace App\Http\Requests;

use App\Enums\BarcodePrintFormat;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBarcodePrintJobRequest extends FormRequest
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
            'format' => ['required', Rule::enum(BarcodePrintFormat::class)],
        ];
    }
}
