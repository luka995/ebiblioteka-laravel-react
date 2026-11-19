<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class ClassCandidateResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $enrollment = $this->relationLoaded('classEnrollments')
            ? $this->classEnrollments->first()
            : null;
        $class = $enrollment?->schoolClass;

        return [
            'id' => $this->id,
            'name' => $this->displayName(),
            'email' => $this->email,
            'username' => $this->username,
            'bar_code' => $this->bar_code,
            'current_class' => $class === null ? null : [
                'id' => $class->id,
                'name' => $class->name,
                'grade_label' => $class->grade?->getLabel(),
            ],
        ];
    }
}
