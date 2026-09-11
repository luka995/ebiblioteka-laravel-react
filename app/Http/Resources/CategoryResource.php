<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\InteractsWithResourceAbilities;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Category
 */
class CategoryResource extends JsonResource
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
            'library_id' => $this->library_id,
            'library_name' => $this->whenLoaded('library', fn () => $this->library->name),
            'parent_id' => $this->parent_id,
            'parent_name' => $this->whenLoaded('parent', fn () => $this->parent?->name),
            'parent_full_name' => $this->whenLoaded('parent', fn () => $this->parent?->fullName()),
            'full_name' => $this->fullName(),
            'children_count' => $this->whenCounted('children'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'can' => $this->abilities($request->user(), ['view', 'update', 'delete']),
        ];
    }
}
