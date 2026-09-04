<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\InteractsWithResourceAbilities;
use App\Models\Region;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Region
 */
class RegionResource extends JsonResource
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
            'places_count' => $this->whenCounted('places'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'can' => $this->abilities($request->user(), ['view', 'update', 'delete']),
        ];
    }
}
