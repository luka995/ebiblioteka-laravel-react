<?php

namespace App\Http\Resources;

use App\Enums\BarcodePrintFormat;
use App\Enums\BarcodePrintJobStatus;
use App\Http\Resources\Concerns\InteractsWithResourceAbilities;
use App\Models\BarcodePrintJob;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin BarcodePrintJob
 */
class BarcodePrintJobResource extends JsonResource
{
    use InteractsWithResourceAbilities;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $status = $this->status instanceof BarcodePrintJobStatus
            ? $this->status
            : BarcodePrintJobStatus::from((string) $this->status);

        $format = $this->format instanceof BarcodePrintFormat
            ? $this->format
            : BarcodePrintFormat::from((string) $this->format);

        return [
            'id' => $this->id,
            'library_id' => $this->library_id,
            'library_name' => $this->whenLoaded('library', fn () => $this->library?->name),
            'requested_by' => $this->whenLoaded('user', fn () => $this->user?->name),
            'scope' => $this->scope,
            'format' => $format->value,
            'status' => $status->value,
            'file_size' => $this->file_size,
            'items_count' => $this->items_count,
            'invalid_count' => $this->invalid_count,
            'started_at' => $this->started_at?->format('d.m.Y. H:i'),
            'finished_at' => $this->finished_at?->format('d.m.Y. H:i'),
            'failure_reason' => $this->failure_reason,
            'created_at' => $this->created_at?->format('d.m.Y. H:i'),
            'can' => $this->abilities($request->user(), ['view', 'download', 'delete']),
        ];
    }
}
