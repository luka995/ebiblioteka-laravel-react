<?php

namespace App\Services;

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
     * Sinhronizuje aktivna članstva iz edit forme:
     * - aktivna koja više nisu izabrana se trajno uklanjaju,
     * - izabrana se dodaju, a postojeća deaktivirana se reaktiviraju,
     * - deaktivirana koja ostaju deaktivirana se ne diraju.
     *
     * @param  array<int, int>  $libraryIds
     */
    public function syncMemberships(User $user, array $libraryIds): void
    {
        $libraryIds = array_values(array_unique(array_map('intval', $libraryIds)));

        $current = $user->librariesWithTrashed()->get()->keyBy('id');

        DB::transaction(function () use ($user, $libraryIds, $current) {
            foreach ($current as $id => $library) {
                $pivot = $library->pivot;

                if ($pivot !== null && ! $pivot->trashed() && ! in_array($id, $libraryIds, true)) {
                    $pivot->forceDelete();
                }
            }

            foreach ($libraryIds as $id) {
                if (! $current->has($id)) {
                    $user->libraries()->attach($id);

                    continue;
                }

                $pivot = $current->get($id)->pivot;

                if ($pivot?->trashed()) {
                    $pivot->restore();
                }
            }
        });
    }
}
