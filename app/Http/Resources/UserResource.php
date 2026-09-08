<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\InteractsWithResourceAbilities;
use App\Models\User;
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
        return [
            'id' => $this->id,
            'name' => $this->displayName(),
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'username' => $this->username,
            'email' => $this->email,
            'role' => $this->role?->value,
            'role_label' => $this->role?->getLabel(),
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
                fn () => $this->libraries->map(fn ($library) => [
                    'id' => $library->id,
                    'name' => $library->name,
                ])
            ),
            'deactivated_libraries' => $this->whenLoaded(
                'librariesWithTrashed',
                fn () => $this->librariesWithTrashed
                    ->filter(fn ($library) => $library->pivot?->trashed())
                    ->values()
                    ->map(fn ($library) => [
                        'id' => $library->id,
                        'name' => $library->name,
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
}
