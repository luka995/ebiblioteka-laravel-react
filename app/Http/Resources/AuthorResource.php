<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\InteractsWithResourceAbilities;
use App\Models\Author;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Author
 */
class AuthorResource extends JsonResource
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
            'display_name' => $this->displayName(),
            'library_id' => $this->library_id,
            'library_name' => $this->whenLoaded('library', fn () => $this->library->name),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'can' => $this->abilities($request->user(), ['view', 'update', 'delete']),
        ];
    }
}
