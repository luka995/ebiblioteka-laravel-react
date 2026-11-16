<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLibraryRequestRequest extends FormRequest
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
            'school_name' => ['required', 'string', 'max:200'],
            'school_type' => ['required', 'string', Rule::in(array_keys((array) config('public_site.school_types', [])))],
            'city' => ['required', 'string', 'max:120'],
            'address' => ['required', 'string', 'max:200'],
            'contact_name' => ['required', 'string', 'max:120'],
            'contact_email' => ['required', 'email', 'max:255'],
            'contact_phone' => ['required', 'string', 'max:50'],
            'student_count' => ['nullable', 'integer', 'min:0', 'max:20000'],
            'employee_count' => ['nullable', 'integer', 'min:0', 'max:5000'],
            'message' => ['nullable', 'string', 'max:3000'],
            'turnstile_token' => ['nullable', 'string'],
        ];
    }
}
