<?php

namespace App\Services;

use App\Models\Library;
use App\Models\News;
use App\Models\Place;
use App\Models\Region;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Jedini sloj koji prevodi Laravel Policy rezultate u API oblike:
 * - `resourceCan()`      -> per-resource `can` mapa (ima instancu modela)
 * - `collectionPermissions()` -> collection-level `permissions` (class-level)
 * - `globalPermissions()` -> globalne permisije za nav (`/me`)
 */
class AuthorizationService
{
    /**
     * @param  array<int, string>  $actions
     * @return array<string, bool>
     */
    public function resourceCan(?User $actor, Model $model, array $actions): array
    {
        if (! $actor) {
            return array_fill_keys($actions, false);
        }

        return collect($actions)
            ->mapWithKeys(fn (string $action) => [$action => $actor->can($action, $model)])
            ->all();
    }

    /**
     * @param  class-string<Model>  $modelClass
     * @return array{viewAny: bool, create: bool}
     */
    public function collectionPermissions(?User $actor, string $modelClass): array
    {
        if (! $actor) {
            return ['viewAny' => false, 'create' => false];
        }

        return [
            'viewAny' => $actor->can('viewAny', $modelClass),
            'create' => $actor->can('create', $modelClass),
        ];
    }

    /**
     * Globalne permisije po sekcijama (koristi se za sidebar navigaciju).
     *
     * @return array<string, array{viewAny: bool, create: bool}>
     */
    public function globalPermissions(User $actor): array
    {
        return [
            'users' => $this->collectionPermissions($actor, User::class),
            'libraries' => $this->collectionPermissions($actor, Library::class),
            'regions' => $this->collectionPermissions($actor, Region::class),
            'places' => $this->collectionPermissions($actor, Place::class),
            'tags' => $this->collectionPermissions($actor, Tag::class),
            'news' => $this->collectionPermissions($actor, News::class),
        ];
    }
}
