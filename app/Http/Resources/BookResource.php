<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\InteractsWithResourceAbilities;
use App\Models\Book;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Book
 */
class BookResource extends JsonResource
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
            'slug' => $this->slug,
            'library_id' => $this->library_id,
            'library_name' => $this->whenLoaded('library', fn () => $this->library->name),
            'inv_number_auto' => $this->whenLoaded('library', fn () => $this->library->inv_number_auto),
            'category_primary_id' => $this->category_primary_id,
            'category_primary_name' => $this->whenLoaded('categoryPrimary', fn () => $this->categoryPrimary?->name),
            'category_secondary_id' => $this->category_secondary_id,
            'category_secondary_name' => $this->whenLoaded('categorySecondary', fn () => $this->categorySecondary?->name),
            'description' => $this->description,
            'image' => $this->image,
            'image_url' => $this->image_url,
            'cover_url' => $this->cover_url,
            'authors' => $this->whenLoaded('authors', fn () => $this->authors->map(fn ($author): array => [
                'id' => $author->id,
                'name' => $author->name,
                'display_name' => $author->displayName(),
            ])->values()),
            'copies_count' => $this->whenCounted('copies_count'),
            'available_count' => $this->whenCounted('available_count'),
            'deleted_at' => $this->deleted_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'can' => $this->abilities($request->user(), ['view', 'update', 'delete', 'restore', 'forceDelete']),
        ];
    }
}
