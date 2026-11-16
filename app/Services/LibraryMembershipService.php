<?php

namespace App\Services;

use App\Enums\LibraryRole;
use App\Models\Library;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Životni ciklus članstva korisnika u bibliotekama (many-to-many pivot).
 *
 * Razlikuje tri operacije na pivot redu `library_user`:
 * - deaktivacija (soft delete, postavlja `deleted_at`),
 * - aktivacija (restore trashed reda),
 * - trajno uklanjanje (forceDelete reda).
 */
class LibraryMembershipService
{
    /**
     * Deaktivira biblioteku i kaskadno deaktivira članstva svih njenih korisnika.
     */
    public function deactivateLibrary(Library $library): void
    {
        DB::transaction(function () use ($library) {
            $library->update(['deleted' => true]);

            $library->users()->detach();
        });
    }

    /**
     * Reaktivira biblioteku i njena članstva.
     */
    public function restoreLibrary(Library $library): void
    {
        DB::transaction(function () use ($library) {
            $library->update(['deleted' => false]);

            $library->usersWithTrashed()->get()->each(function (User $user) {
                if ($user->pivot?->trashed()) {
                    $user->pivot->restore();
                }
            });
        });
    }

    /**
     * Deaktivira (soft delete) članstva korisnika u datim bibliotekama.
     *
     * @param  array<int, int>  $libraryIds
     */
    public function deactivateMemberships(User $user, array $libraryIds): void
    {
        DB::transaction(function () use ($user, $libraryIds) {
            $user->libraries()->detach($libraryIds);
        });
    }

    /**
     * Aktivira (restore) članstva korisnika u datim bibliotekama.
     *
     * @param  array<int, int>  $libraryIds
     */
    public function activateMemberships(User $user, array $libraryIds): void
    {
        DB::transaction(function () use ($user, $libraryIds) {
            $user->librariesWithTrashed()
                ->whereKey($libraryIds)
                ->get()
                ->each(function (Library $library) {
                    if ($library->pivot?->trashed()) {
                        $library->pivot->restore();
                    }
                });
        });
    }

    /**
     * Trajno uklanja pivot redove članstva (bez obzira na status).
     *
     * @param  array<int, int>  $libraryIds
     */
    public function removeMemberships(User $user, array $libraryIds): void
    {
        DB::transaction(function () use ($user, $libraryIds) {
            $user->librariesWithTrashed()
                ->whereKey($libraryIds)
                ->get()
                ->each(function (Library $library) {
                    $library->pivot?->forceDelete();
                });
        });
    }

    /**
     * Sinhronizuje aktivna članstva i njihove uloge iz edit forme.
     *
     * @param  array<int, LibraryRole|string>  $memberships  library_id => role
     */
    public function syncMemberships(User $user, array $memberships): void
    {
        $desired = [];

        foreach ($memberships as $key => $value) {
            // Podrzan je i stari format [library_id, library_id, ...] radi
            // kompatibilnosti sa testovima i eventualnim internim pozivima.
            if (! $value instanceof LibraryRole
                && is_int($key)
                && (is_int($value) || ctype_digit((string) $value))) {
                $desired[(int) $value] = LibraryRole::User;

                continue;
            }

            $desired[(int) $key] = $value instanceof LibraryRole
                ? $value
                : LibraryRole::from((string) $value);
        }

        $current = $user->librariesWithTrashed()->get()->keyBy('id');

        DB::transaction(function () use ($user, $desired, $current): void {
            foreach ($current as $id => $library) {
                $pivot = $library->pivot;

                if ($pivot !== null && ! $pivot->trashed() && ! array_key_exists($id, $desired)) {
                    $pivot->forceDelete();
                }
            }

            foreach ($desired as $id => $role) {
                if (! $current->has($id)) {
                    $user->libraries()->attach($id, ['role' => $role->value]);

                    continue;
                }

                $pivot = $current->get($id)->pivot;

                if ($pivot?->trashed()) {
                    $pivot->restore();
                }

                $pivot?->update(['role' => $role->value]);
            }
        });
    }
}
