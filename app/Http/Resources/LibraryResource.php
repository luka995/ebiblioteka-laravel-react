<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\InteractsWithResourceAbilities;
use App\Models\Library;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Library
 */
class LibraryResource extends JsonResource
{
    use InteractsWithResourceAbilities;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'address' => $this->address,
            'work_time' => $this->work_time,
            'inv_number_auto' => (bool) $this->inv_number_auto,
            'deleted' => (bool) $this->deleted,
            'place_id' => $this->place_id,
            'place' => $this->whenLoaded('place', fn () => [
                'id' => $this->place->id,
                'name' => $this->place->name,
                'region_id' => $this->place->region_id,
                'region' => $this->place->relationLoaded('region')
                    ? [
                        'id' => $this->place->region->id,
                        'name' => $this->place->region->name,
                    ]
                    : null,
            ]),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'can' => $this->abilities($request->user(), ['view', 'update', 'delete', 'restore']),
        ];
    }
}
