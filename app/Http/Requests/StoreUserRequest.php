<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreUserRequest extends FormRequest
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
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'username' => ['nullable', 'string', 'max:255', 'regex:/^[a-zA-Z0-9_.-]+$/', 'unique:users,username'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', Rule::enum(UserRole::class)],
            'jmbg' => ['nullable', 'string', 'digits:13'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'post_code' => ['nullable', 'string', 'max:20'],
            'bar_code' => ['nullable', 'string', 'digits:13', 'unique:users,bar_code'],
            'libraries' => ['nullable', 'array'],
            'libraries.*' => ['integer', Rule::exists('libraries', 'id')],
        ];
    }

    /**
     * Role koje akter sme da dodeli ogranicene su ulogom aktera.
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $requested = UserRole::from($this->input('role'));

                if (! in_array($requested, UserRole::assignableBy($this->user()->role), true)) {
                    $validator->errors()->add('role', __('validation.custom.role_not_assignable'));
                }
            },
        ];
    }
}
