<?php

use App\Enums\BarcodePrintFormat;
use App\Enums\BarcodePrintJobStatus;
use App\Enums\UserRole;
use App\Jobs\GenerateBarcodePrintPdf;
use App\Models\BarcodePrintJob;
use App\Models\Book;
use App\Models\BookCopy;
use App\Models\Library;
use App\Models\User;
use App\Services\BarcodePrintService;
use App\Support\BarCode;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

function barcodeJobRequestHeaders(): array
{
    return [
        'Origin' => 'http://localhost:3001',
        'Accept' => 'application/json',
    ];
}

function barcodeJobStaff(Library $library, UserRole $role = UserRole::Librarian): User
{
    $user = User::factory()->role($role)->create();
    $user->libraries()->attach($library->id);

    return $user;
}

function barcodeJobRecord(Library $library, array $attributes = []): BarcodePrintJob
{
    return BarcodePrintJob::create(array_merge([
        'library_id' => $library->id,
        'user_id' => User::factory()->create()->id,
        'scope' => BarcodePrintJob::SCOPE_LIBRARY,
        'format' => BarcodePrintFormat::A4,
        'status' => BarcodePrintJobStatus::Pending,
    ], $attributes));
}

function barcodeJobCopy(Library $library, string $orderNumber, ?string $barcode = null, ?Book $book = null): BookCopy
{
    $book ??= Book::factory()->for($library)->create(['name' => 'Naslov '.$orderNumber]);

    return BookCopy::factory()
        ->for($library)
        ->for($book)
        ->create([
            'order_number' => $orderNumber,
            'barcode' => $barcode ?? BarCode::generateFromBaseNumber($orderNumber),
        ]);
}

test('store creates a pending job and dispatches generation to the print queue', function () {
    Queue::fake();

    $admin = User::factory()->superAdmin()->create();
    $library = Library::factory()->create();
    $this->withSession(['active_library_id' => $library->id]);

    $response = $this->actingAs($admin)->postJson('/api/v1/barcode-print-jobs', [
        'format' => BarcodePrintFormat::A4->value,
    ], barcodeJobRequestHeaders());

    $response->assertCreated()
        ->assertJsonPath('data.status', BarcodePrintJobStatus::Pending->value)
        ->assertJsonPath('data.format', BarcodePrintFormat::A4->value)
        ->assertJsonPath('data.library_id', $library->id);

    Queue::assertPushedOn('print', GenerateBarcodePrintPdf::class);

    expect(BarcodePrintJob::where('library_id', $library->id)->count())->toBe(1);
});

test('store rejects an unknown label format', function () {
    Queue::fake();

    $admin = User::factory()->superAdmin()->create();
    $library = Library::factory()->create();
    $this->withSession(['active_library_id' => $library->id]);

    $this->actingAs($admin)->postJson('/api/v1/barcode-print-jobs', [
        'format' => 'unknown',
    ], barcodeJobRequestHeaders())->assertStatus(422);

    Queue::assertNothingPushed();
});

test('store requires an active library for a librarian', function () {
    Queue::fake();

    $user = User::factory()->role(UserRole::Librarian)->create();

    $this->actingAs($user)->postJson('/api/v1/barcode-print-jobs', [
        'format' => BarcodePrintFormat::Label->value,
    ], barcodeJobRequestHeaders())->assertStatus(422);

    Queue::assertNothingPushed();
});

test('index is scoped to the active library', function () {
    $library = Library::factory()->create();
    $otherLibrary = Library::factory()->create();

    $user = barcodeJobStaff($library);
    barcodeJobRecord($library);
    barcodeJobRecord($otherLibrary);

    $response = $this->actingAs($user)->getJson('/api/v1/barcode-print-jobs', barcodeJobRequestHeaders());

    $response->assertOk()->assertJsonCount(1, 'data');
    expect($response->json('data.0.library_id'))->toBe($library->id);
});

test('a librarian cannot view another library job', function () {
    $library = Library::factory()->create();
    $otherLibrary = Library::factory()->create();
    $user = barcodeJobStaff($library);
    $job = barcodeJobRecord($otherLibrary);

    $this->actingAs($user)->getJson("/api/v1/barcode-print-jobs/{$job->id}", barcodeJobRequestHeaders())
        ->assertForbidden();
});

test('download is rejected while the job is not completed', function () {
    $library = Library::factory()->create();
    $user = barcodeJobStaff($library);
    $job = barcodeJobRecord($library);

    $this->actingAs($user)->getJson("/api/v1/barcode-print-jobs/{$job->id}/download", barcodeJobRequestHeaders())
        ->assertStatus(409);
});

test('the job generates a pdf for all active copies and skips invalid barcodes', function () {
    Storage::fake('barcode');

    $library = Library::factory()->create();
    barcodeJobCopy($library, '1');
    barcodeJobCopy($library, '2');
    barcodeJobCopy($library, '3', '123');

    $archivedCopy = barcodeJobCopy($library, '4');
    $archivedCopy->delete();

    $archivedBook = Book::factory()->for($library)->create(['name' => 'Arhiviran naslov']);
    barcodeJobCopy($library, '5', null, $archivedBook);
    $archivedBook->delete();

    $job = barcodeJobRecord($library);

    (new GenerateBarcodePrintPdf($job))->handle(app(BarcodePrintService::class));

    $job->refresh();

    expect($job->status)->toBe(BarcodePrintJobStatus::Completed)
        ->and($job->items_count)->toBe(2)
        ->and($job->invalid_count)->toBe(1)
        ->and($job->file_path)->not->toBeNull();

    Storage::disk('barcode')->assertExists($job->file_path);

    $response = $this->actingAs(barcodeJobStaff($library))
        ->getJson("/api/v1/barcode-print-jobs/{$job->id}/download", barcodeJobRequestHeaders());

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('application/pdf');
});

test('the job records a failure when there are no printable copies', function () {
    Storage::fake('barcode');

    $library = Library::factory()->create();
    $job = barcodeJobRecord($library);

    try {
        (new GenerateBarcodePrintPdf($job))->handle(app(BarcodePrintService::class));
    } catch (Throwable) {
        // Ocekivano: posao baca gresku i status ostaje failed.
    }

    $job->refresh();

    expect($job->status)->toBe(BarcodePrintJobStatus::Failed)
        ->and($job->failure_reason)->not->toBeNull()
        ->and($job->file_path)->toBeNull();
});

test('destroy removes the generated pdf and the job record', function () {
    Storage::fake('barcode');

    $library = Library::factory()->create();
    $user = barcodeJobStaff($library);
    $job = barcodeJobRecord($library, [
        'status' => BarcodePrintJobStatus::Completed,
        'file_path' => "{$library->id}/barkodovi_1.pdf",
        'items_count' => 3,
    ]);

    Storage::disk('barcode')->put($job->file_path, 'pdf');

    $this->actingAs($user)
        ->deleteJson("/api/v1/barcode-print-jobs/{$job->id}", [], barcodeJobRequestHeaders())
        ->assertNoContent();

    expect(BarcodePrintJob::find($job->id))->toBeNull();
    Storage::disk('barcode')->assertMissing($job->file_path);
});
