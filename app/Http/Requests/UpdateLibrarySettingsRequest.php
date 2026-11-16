<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateLibrarySettingsRequest extends FormRequest
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
            'reservation_hours' => ['required', 'integer', 'min:1', 'max:720'],
            'lend_days' => ['sometimes', 'required', 'integer', 'min:1', 'max:365'],
            'overdue_price_per_day' => ['sometimes', 'required', 'numeric', 'min:0', 'max:100000'],
        ];
    }
}
