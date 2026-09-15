<?php

namespace App\Services;

use App\Models\Library;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\ValidationException;

/**
 * Serverski kontekst "aktivne biblioteke" korisnika.
 *
 * Superadmin po defaultu nema aktivnu biblioteku (vidi sve), ali moze
 * opciono da izabere jednu kako bi suzio prikaz. Ostali korisnici biraju
 * iz skupa svojih pivot biblioteka; korisnik sa tacno jednom bibliotekom
 * se automatski postavlja u nju.
 */
class ActiveLibraryService
{
    private const SESSION_KEY = 'active_library_id';

    /**
     * Biblioteke koje korisnik sme da izabere kao aktivnu.
     *
     * @return Collection<int, Library>
     */
    public function selectable(User $user): Collection
    {
        if ($user->isSuperAdmin()) {
            return Library::query()
                ->where('deleted', false)
                ->orderBy('name')
                ->orderBy('id')
                ->get();
        }

        return $user->libraries()
            ->where('libraries.deleted', false)
            ->orderBy('name')
            ->orderBy('id')
            ->get();
    }

    /**
     * Efektivna aktivna biblioteka (sa fallback-om), ili null.
     */
    public function resolve(User $user): ?Library
    {
        $id = (int) Session::get(self::SESSION_KEY);

        if ($id > 0) {
            $library = $this->selectable($user)->firstWhere('id', $id);

            if ($library instanceof Library) {
                return $library;
            }
        }

        if (! $user->isSuperAdmin()) {
            $libraries = $this->selectable($user);

            if ($libraries->count() === 1) {
                return $libraries->first();
            }
        }

        return null;
    }

    /**
     * Scope-uje upit korisnika na biblioteke kojima akter sme da pristupi.
     *
     * Superadmin vidi sve (ili aktivnu biblioteku ako je izabrao); ostali
     * akteri uvek vide samo svoje biblioteke.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public function scopeUsers(Builder $query, User $actor): Builder
    {
        $active = $this->resolve($actor);

        if ($active instanceof Library) {
            return $query->whereHas('libraries', fn (Builder $q) => $q->whereKey($active->id));
        }

        if (! $actor->isSuperAdmin()) {
            $ids = $this->selectable($actor)->pluck('id');

            return $query->whereHas('libraries', fn (Builder $q) => $q->whereIn('libraries.id', $ids));
        }

        return $query;
    }

    /**
     * Postavlja (ili brise) aktivnu biblioteku u sesiji.
     *
     * @throws ValidationException
     */
    public function set(User $user, ?int $libraryId): void
    {
        if ($libraryId === null) {
            if (! $user->isSuperAdmin()) {
                throw ValidationException::withMessages([
                    'library_id' => __('validation.custom.active_library_required'),
                ]);
            }

            Session::forget(self::SESSION_KEY);

            return;
        }

        $library = $this->selectable($user)->firstWhere('id', $libraryId);

        if (! $library instanceof Library) {
            throw ValidationException::withMessages([
                'library_id' => __('validation.custom.active_library_invalid'),
            ]);
        }

        Session::put(self::SESSION_KEY, $library->id);
    }
}
