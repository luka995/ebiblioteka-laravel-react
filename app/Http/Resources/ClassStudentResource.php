<?php

namespace App\Http\Resources;

use App\Models\ClassEnrollment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ClassEnrollment
 */
class ClassStudentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'name' => $this->whenLoaded('user', fn () => $this->user?->displayName()),
            'email' => $this->whenLoaded('user', fn () => $this->user?->email),
            'username' => $this->whenLoaded('user', fn () => $this->user?->username),
            'bar_code' => $this->whenLoaded('user', fn () => $this->user?->bar_code),
            'assigned_at' => $this->updated_at?->toIso8601String(),
            'assigned_at_formatted' => $this->updated_at?->format('d.m.Y. H:i'),
            'assigned_by' => $this->whenLoaded('assignedBy', fn () => $this->assignedBy?->displayName()),
        ];
    }
}
