<?php

use App\Exceptions\InventoryNumberCollisionException;
use App\Models\Book;
use App\Models\BookCopy;
use App\Models\Library;
use App\Services\InventoryNumberService;
use App\Services\InventoryReconciliationService;
use App\Support\BarCode;
use Illuminate\Validation\ValidationException;

function inventoryNumberService(): InventoryNumberService
{
    return app(InventoryNumberService::class);
}

function inventoryReconciliationService(): InventoryReconciliationService
{
    return app(InventoryReconciliationService::class);
}

function makeInventoryLibrary(): Library
{
    return Library::factory()->create();
}

function makeInventoryCopy(Library $library, string $orderNumber, ?Book $book = null): BookCopy
{
    $book ??= Book::factory()->for($library)->create();

    return BookCopy::factory()->for($library)->for($book)->create([
        'order_number' => $orderNumber,
    ]);
}

test('auto inventory number increments the per-library sequence', function () {
    $library = makeInventoryLibrary();
    $service = inventoryNumberService();

    expect($service->next($library))->toBe('1')
        ->and($service->next($library))->toBe('2')
        ->and($library->inventorySequence()->first()->last_number)->toBe(2);
});

test('auto inventory number is unique per library', function () {
    $a = makeInventoryLibrary();
    $b = makeInventoryLibrary();
    $service = inventoryNumberService();

    expect($service->next($a))->toBe('1')
        ->and($service->next($b))->toBe('1');
});

test('auto number colliding with an existing copy is rejected', function () {
    $library = makeInventoryLibrary();
    $service = inventoryNumberService();
    makeInventoryCopy($library, '1');

    expect(fn () => $service->next($library))->toThrow(InventoryNumberCollisionException::class);
});

test('barcode is derived from the inventory number as a valid EAN-13', function () {
    $service = inventoryNumberService();

    $barcode = $service->barcodeFor('7');

    expect($barcode)->toHaveLength(13)
        ->and(BarCode::validate($barcode))->toBeTrue();
});

test('inventory number longer than twelve digits is rejected for barcode generation', function () {
    expect(fn () => inventoryNumberService()->barcodeFor('1234567890123'))
        ->toThrow(InvalidArgumentException::class);
});

test('manual validation rejects duplicate inventory numbers', function () {
    $library = makeInventoryLibrary();
    makeInventoryCopy($library, '5');

    expect(fn () => inventoryReconciliationService()->validateManual($library, '5'))
        ->toThrow(ValidationException::class);
});

test('manual validation rejects numbers that skip ahead', function () {
    $library = makeInventoryLibrary();
    makeInventoryCopy($library, '5');

    expect(fn () => inventoryReconciliationService()->validateManual($library, '9'))
        ->toThrow(ValidationException::class);
});

test('manual validation allows filling existent gaps and the next number', function () {
    $library = makeInventoryLibrary();
    makeInventoryCopy($library, '2');
    makeInventoryCopy($library, '4');
    $service = inventoryReconciliationService();

    expect($service->validateManual($library, '1'))->toBe('1')
        ->and($service->validateManual($library, '3'))->toBe('3')
        ->and($service->validateManual($library, '5'))->toBe('5');
});

test('maxUsed includes archived copies while maxExisting does not', function () {
    $library = makeInventoryLibrary();
    makeInventoryCopy($library, '2');
    $archived = makeInventoryCopy($library, '9');
    $archived->delete();

    $service = inventoryNumberService();

    expect($service->maxExisting($library))->toBe(2)
        ->and($service->maxUsed($library))->toBe(9);
});

test('inspect reports a discrepancy when archived numbers are ahead of the sequence', function () {
    $library = makeInventoryLibrary();
    makeInventoryCopy($library, '2');
    $archived = makeInventoryCopy($library, '9');
    $archived->delete();

    $discrepancy = inventoryReconciliationService()->inspect($library);

    expect($discrepancy)->not->toBeNull()
        ->and($discrepancy->nextAuto)->toBe(1)
        ->and($discrepancy->maxExisting)->toBe(2)
        ->and($discrepancy->maxUsed)->toBe(9)
        ->and($discrepancy->archivedCandidates)->toHaveCount(1);
});

test('inspect returns null when the sequence is aligned', function () {
    $library = makeInventoryLibrary();
    $service = inventoryNumberService();
    $service->next($library);
    $service->next($library);

    expect(inventoryReconciliationService()->inspect($library))->toBeNull();
});

test('sync aligns the sequence to the greatest non-archived number', function () {
    $library = makeInventoryLibrary();
    makeInventoryCopy($library, '4');
    $archived = makeInventoryCopy($library, '9');
    $archived->delete();
    $archived->forceDelete();

    $max = inventoryNumberService()->syncToMaxExisting($library);

    expect($max)->toBe(4)
        ->and($library->inventorySequence()->first()->last_number)->toBe(4)
        ->and(inventoryReconciliationService()->inspect($library))->toBeNull();
});

test('recordManual never moves the sequence backwards', function () {
    $library = makeInventoryLibrary();
    $service = inventoryNumberService();
    $service->next($library);
    $service->next($library);
    $service->next($library);

    $service->recordManual($library, '2');

    expect($library->inventorySequence()->first()->last_number)->toBe(3);
});
