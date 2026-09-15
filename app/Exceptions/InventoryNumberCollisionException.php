<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Baceno kada automatski generisan inventarni broj kolidira sa postojecim
 * zapisom (aktivnim ili arhiviranim). Znaci da arhiva mora prvo da se resi.
 */
class InventoryNumberCollisionException extends RuntimeException
{
    public function __construct(public readonly string $orderNumber)
    {
        parent::__construct("Inventory number [{$orderNumber}] is already in use.");
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => __('validation.custom.inventory_reconciliation_required'),
        ], 409);
    }
}
