<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\InteractsWithResourceAbilities;
use App\Models\User;
use App\Services\UserAccessContext;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class UserResource extends JsonResource
{
    use InteractsWithResourceAbilities;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $isSuperAdmin = $this->isSuperAdmin();
        $viewer = $request->user();
        $visibleLibraryIds = $this->visibleLibraryIds($viewer);

        $visibleLibraries = $this->relationLoaded('libraries')
            ? $this->libraries->filter(fn ($library) => $visibleLibraryIds === null || in_array((int) $library->id, $visibleLibraryIds, true))
            : null;

        $primaryRole = $visibleLibraries
            ? $visibleLibraries
                ->map(fn ($library) => $library->pivot?->role)
                ->filter()
                ->sortByDesc(fn ($role) => match ($role->value) {
                    'library_admin' => 3,
                    'librarian' => 2,
                    default => 1,
                })
                ->first()
            : null;

        return [
            'id' => $this->id,
            'name' => $this->displayName(),
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'username' => $this->username,
            'email' => $this->email,
            'locale' => $this->locale,
            'is_super_admin' => $isSuperAdmin,
            'role' => $primaryRole?->value,
            'role_label' => $isSuperAdmin ? __('roles.superadmin') : $primaryRole?->getLabel(),
            'jmbg' => $this->jmbg,
            'address' => $this->address,
            'city' => $this->city,
            'post_code' => $this->post_code,
            'bar_code' => $this->bar_code,
            'email_verified_at' => $this->email_verified_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'libraries' => $this->whenLoaded(
                'libraries',
                fn () => $visibleLibraries->map(fn ($library) => [
                    'id' => $library->id,
                    'name' => $library->name,
                    'role' => $library->pivot?->role?->value,
                    'role_label' => $library->pivot?->role?->getLabel(),
                ])->values()
            ),
            'deactivated_libraries' => $this->whenLoaded(
                'librariesWithTrashed',
                fn () => $this->librariesWithTrashed
                    ->filter(fn ($library) => $library->pivot?->trashed())
                    ->filter(fn ($library) => $visibleLibraryIds === null || in_array((int) $library->id, $visibleLibraryIds, true))
                    ->values()
                    ->map(fn ($library) => [
                        'id' => $library->id,
                        'name' => $library->name,
                        'role' => $library->pivot?->role?->value,
                        'role_label' => $library->pivot?->role?->getLabel(),
                    ])
            ),
            'tags' => $this->whenLoaded(
                'tags',
                fn () => $this->tags->map(fn ($tag) => [
                    'id' => $tag->id,
                    'name' => $tag->name,
                    'library_id' => $tag->library_id,
                ])
            ),
            'can' => $this->abilities($request->user(), ['view', 'update', 'delete', 'forceDelete', 'manageMemberships', 'removeMemberships']),
        ];
    }

    /**
     * Superadmin i sam korisnik vide sve biblioteke. Administrator biblioteke
     * vidi samo biblioteke u kojima je on administrator.
     *
     * @return array<int, int>|null
     */
    private function visibleLibraryIds(?User $viewer): ?array
    {
        if ($viewer === null || $viewer->id === $this->id || $viewer->isSuperAdmin()) {
            return null;
        }

        return collect(app(UserAccessContext::class)->roles($viewer))
            ->filter(fn ($role): bool => $role->isLibraryAdmin())
            ->keys()
            ->map(fn (int|string $id): int => (int) $id)
            ->values()
            ->all();
    }
}
