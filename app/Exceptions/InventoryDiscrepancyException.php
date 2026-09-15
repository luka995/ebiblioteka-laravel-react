<?php

namespace App\Exceptions;

use App\Http\Resources\BookCopyResource;
use App\Support\InventoryDiscrepancy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Blokira kreiranje kopije kada postoji razlika u inventarnim brojevima.
 *
 * Vraca 409 sa opisom discrepancy-ja i arhiviranim kandidatima; resavanje ide
 * na ekranu Arhive.
 */
class InventoryDiscrepancyException extends RuntimeException
{
    public function __construct(public readonly InventoryDiscrepancy $discrepancy)
    {
        parent::__construct('Inventory numbers are not aligned; resolve the archive first.');
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => __('validation.custom.inventory_reconciliation_required'),
            'inventory_discrepancy' => [
                'next_auto' => $this->discrepancy->nextAuto,
                'max_existing' => $this->discrepancy->maxExisting,
                'max_used' => $this->discrepancy->maxUsed,
                'archived_copies' => BookCopyResource::collection($this->discrepancy->archivedCandidates)->resolve($request),
            ],
        ], 409);
    }
}
