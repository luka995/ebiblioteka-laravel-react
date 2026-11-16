<?php

namespace App\Services;

use App\Enums\LibraryRole;
use App\Models\User;

/**
 * Request/job-scoped keš pristupa korisnika.
 *
 * Za svakog korisnika najviše jednom u requestu/job-u učitava:
 * - da li je globalni superadmin (iz settings),
 * - uloge po bibliotekama (iz library_user.role).
 *
 * Servis ne čita Auth::user() i ne koristi sesiju. Akter se uvek prosledjuje
 * eksplicitno, pa isti kod radi u HTTP requestu, queue job-u i komandi.
 */
class UserAccessContext
{
    /** @var array<int, array{super: bool, roles: array<int, LibraryRole>}> */
    private array $cache = [];

    public function __construct(private readonly GlobalSettings $settings) {}

    public function isSuperAdmin(User $user): bool
    {
        return $this->for($user)['super'];
    }

    public function roleIn(User $user, int $libraryId): ?LibraryRole
    {
        return $this->for($user)['roles'][$libraryId] ?? null;
    }

    /**
     * @return array<int, LibraryRole>
     */
    public function roles(User $user): array
    {
        return $this->for($user)['roles'];
    }

    public function isMemberOf(User $user, int $libraryId): bool
    {
        return array_key_exists($libraryId, $this->for($user)['roles']);
    }

    public function isStaffIn(User $user, int $libraryId): bool
    {
        return $this->roleIn($user, $libraryId)?->isStaff() ?? false;
    }

    public function isLibraryAdminIn(User $user, int $libraryId): bool
    {
        return $this->roleIn($user, $libraryId)?->isLibraryAdmin() ?? false;
    }

    public function flush(User|int $user): void
    {
        unset($this->cache[$user instanceof User ? $user->id : $user]);
    }

    /**
     * @return array{super: bool, roles: array<int, LibraryRole>}
     */
    private function for(User $user): array
    {
        if (isset($this->cache[$user->id])) {
            return $this->cache[$user->id];
        }

        $roles = [];

        if ($user->relationLoaded('libraries')) {
            foreach ($user->libraries as $library) {
                if ($library->pivot?->role instanceof LibraryRole) {
                    $roles[(int) $library->id] = $library->pivot->role;
                }
            }
        } else {
            $user->libraries()
                ->get()
                ->each(function ($library) use (&$roles): void {
                    if ($library->pivot?->role instanceof LibraryRole) {
                        $roles[(int) $library->id] = $library->pivot->role;
                    }
                });
        }

        return $this->cache[$user->id] = [
            'super' => $this->settings->isSuperAdmin($user),
            'roles' => $roles,
        ];
    }
}
