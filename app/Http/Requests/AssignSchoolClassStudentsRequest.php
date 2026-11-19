<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignSchoolClassStudentsRequest extends FormRequest
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
            'user_ids.*' => ['integer', Rule::exists('users', 'id')],
        ];
    }

    /**
     * @return array<int, int>
     */
    public function userIds(): array
    {
        return array_values(array_unique(array_map('intval', (array) $this->input('user_ids', []))));
    }
}
