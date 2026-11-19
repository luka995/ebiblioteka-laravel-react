<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\InteractsWithResourceAbilities;
use App\Models\SchoolClass;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SchoolClass
 */
class SchoolClassResource extends JsonResource
{
    use InteractsWithResourceAbilities;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'library_id' => $this->library_id,
            'library_name' => $this->whenLoaded('library', fn () => $this->library?->name),
            'school_year_id' => $this->school_year_id,
            'school_year_name' => $this->whenLoaded('schoolYear', fn () => $this->schoolYear?->name),
            'grade' => $this->grade?->value,
            'grade_label' => $this->grade?->getLabel(),
            'grade_full_label' => $this->grade?->getFullLabel(),
            'name' => $this->name,
            'teacher_name' => $this->teacher_name,
            'is_active' => $this->is_active,
            'students_count' => $this->whenCounted('enrollments'),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'can' => $this->abilities($request->user(), [
                'view',
                'update',
                'delete',
                'viewStudents',
                'assignStudents',
                'unassignStudents',
            ]),
        ];
    }
}
