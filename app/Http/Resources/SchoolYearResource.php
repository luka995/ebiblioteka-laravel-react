<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\InteractsWithResourceAbilities;
use App\Models\SchoolYear;
use App\Services\SchoolYearService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SchoolYear
 */
class SchoolYearResource extends JsonResource
{
    use InteractsWithResourceAbilities;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'year' => $this->year,
            'name' => $this->name,
            'starts_on' => $this->starts_on?->toDateString(),
            'starts_on_formatted' => $this->starts_on?->format('d.m.Y.'),
            'ends_on' => $this->ends_on?->toDateString(),
            'ends_on_formatted' => $this->ends_on?->format('d.m.Y.'),
            'is_active' => app(SchoolYearService::class)->isActive($this->resource),
            'classes_count' => $this->whenCounted('classes'),
            'created_by' => $this->whenLoaded(
                'createdBy',
                fn () => $this->createdBy?->displayName()
            ),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'can' => $this->abilities($request->user(), ['update', 'delete', 'activate']),
        ];
    }
}
