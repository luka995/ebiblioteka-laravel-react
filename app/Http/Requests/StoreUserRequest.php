<?php

namespace App\Http\Requests;

use App\Enums\LibraryRole;
use App\Models\Library;
use App\Services\ActiveLibraryService;
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
            'jmbg' => ['nullable', 'string', 'digits:13'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'post_code' => ['nullable', 'string', 'max:20'],
            'libraries' => ['nullable', 'array'],
            'libraries.*.id' => ['required', 'integer', Rule::exists('libraries', 'id')],
            'libraries.*.role' => ['required', Rule::enum(LibraryRole::class)],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $memberships = $this->memberships();

                if ($this->user()->isSuperAdmin()) {
                    return;
                }

                $active = app(ActiveLibraryService::class)->resolve($this->user());

                if (! $active instanceof Library) {
                    $validator->errors()->add('libraries', __('validation.custom.active_library_required'));

                    return;
                }

                $actorRole = $this->user()->roleIn($active->id);

                if ($actorRole === null || ! $actorRole->isLibraryAdmin()) {
                    $validator->errors()->add('libraries', __('validation.custom.library_not_managed'));

                    return;
                }

                foreach ($memberships as $libraryId => $role) {
                    if ($libraryId !== (int) $active->id) {
                        $validator->errors()->add('libraries', __('validation.custom.library_not_managed'));

                        return;
                    }

                    if (! in_array($role, LibraryRole::assignableBy($actorRole), true)) {
                        $validator->errors()->add('libraries', __('validation.custom.role_not_assignable'));

                        return;
                    }
                }
            },
        ];
    }

    /**
     * @return array<int, LibraryRole>
     */
    public function memberships(): array
    {
        $memberships = [];

        foreach ((array) $this->input('libraries', []) as $membership) {
            if (! is_array($membership) || ! isset($membership['id'], $membership['role'])) {
                continue;
            }

            $role = LibraryRole::tryFrom((string) $membership['role']);

            if ($role !== null) {
                $memberships[(int) $membership['id']] = $role;
            }
        }

        return $memberships;
    }
}
