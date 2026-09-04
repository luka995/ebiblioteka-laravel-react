<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateUserRequest extends FormRequest
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
        /** @var User|null $user */
        $user = $this->route('user');

        return [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'username' => [
                'nullable',
                'string',
                'max:255',
                'regex:/^[a-zA-Z0-9_.-]+$/',
                Rule::unique('users', 'username')->ignore($user),
            ],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user),
            ],
            'password' => ['nullable', 'string', 'min:8'],
            'role' => ['required', Rule::enum(UserRole::class)],
            'jmbg' => ['nullable', 'string', 'digits:13'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'post_code' => ['nullable', 'string', 'max:20'],
            'bar_code' => [
                'nullable',
                'string',
                'digits:13',
                Rule::unique('users', 'bar_code')->ignore($user),
            ],
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
            function (Validator $validator) {
                if ($this->user()->isSuperAdmin()) {
                    return;
                }

                $allowed = $this->user()->libraries()->pluck('libraries.id')->all();

                foreach ($this->input('libraries', []) as $libraryId) {
                    if (! in_array((int) $libraryId, $allowed, true)) {
                        $validator->errors()->add('libraries', __('validation.custom.library_not_managed'));

                        return;
                    }
                }
            },
        ];
    }
}
