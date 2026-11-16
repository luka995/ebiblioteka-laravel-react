<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\InteractsWithResourceAbilities;
use App\Models\LibraryRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin LibraryRequest
 */
class LibraryRequestResource extends JsonResource
{
    use InteractsWithResourceAbilities;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'school_name' => $this->school_name,
            'school_type' => $this->school_type,
            'school_type_label' => $this->schoolTypeLabel(),
            'city' => $this->city,
            'address' => $this->address,
            'contact_name' => $this->contact_name,
            'contact_email' => $this->contact_email,
            'contact_phone' => $this->contact_phone,
            'student_count' => $this->student_count,
            'employee_count' => $this->employee_count,
            'message' => $this->message,
            'status' => $this->status->value,
            'status_label' => $this->status->getLabel(),
            'handled_by' => $this->whenLoaded('handledBy', fn () => $this->handledBy?->name),
            'handled_at' => $this->handled_at,
            'handled_at_formatted' => $this->handled_at?->format('d.m.Y. H:i'),
            'created_at' => $this->created_at,
            'created_at_formatted' => $this->created_at?->format('d.m.Y. H:i'),
            'updated_at' => $this->updated_at,
            'can' => $this->abilities($request->user(), ['view', 'approve', 'reject']),
        ];
    }
}
