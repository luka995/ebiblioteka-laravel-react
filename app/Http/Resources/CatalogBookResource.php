<?php

namespace App\Http\Resources;

use App\Models\Book;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Knjiga u korisnickom katalogu. Ne sadrzi staff `can` mapu; vidi se samo
 * ono sto je potrebno za prikaz i rezervaciju.
 *
 * @mixin Book
 */
class CatalogBookResource extends JsonResource
{
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
            'library_name' => $this->whenLoaded('library', fn () => $this->library?->name),
            'library_address' => $this->whenLoaded('library', fn () => $this->library?->address),
            'category_primary_id' => $this->category_primary_id,
            'category_primary_name' => $this->whenLoaded('categoryPrimary', fn () => $this->categoryPrimary?->name),
            'description' => $this->description,
            'image' => $this->image,
            'image_url' => $this->image_url,
            'cover_url' => $this->cover_url,
            'authors' => $this->whenLoaded('authors', fn () => $this->authors->map(fn ($author): array => [
                'id' => $author->id,
                'name' => $author->name,
                'display_name' => $author->displayName(),
            ])->values()),
        ];
    }
}
