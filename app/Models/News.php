<?php

namespace App\Models;

use Database\Factories\NewsFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

#[Fillable(['title', 'slug', 'body', 'date', 'image'])]
class News extends Model
{
    /** @use HasFactory<NewsFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }

    /**
     * Root-relative URL sličice (thumbnail) članka (npr. /storage/news/slika.png),
     * ili null ako slika nije postavljena. Putanja se izvodi iz Laravel-ovog
     * storage URL-a umesto da se hardkoduje, pa poštuje konfiguraciju diska.
     */
    public function getImageUrlAttribute(): ?string
    {
        return $this->image
            ? parse_url(Storage::disk('public')->url($this->image), PHP_URL_PATH)
            : null;
    }
}
