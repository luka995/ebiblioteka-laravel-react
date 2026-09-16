<?php

use App\Enums\InventoryBookStatus;
use App\Enums\UserRole;
use App\Jobs\GenerateInventoryBookPdf;
use App\Models\Author;
use App\Models\Book;
use App\Models\BookCopy;
use App\Models\InventoryBook;
use App\Models\Library;
use App\Models\User;
use App\Services\InventoryBookPdfService;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

function inventoryHeaders(): array
{
    return [
        'Origin' => 'http://localhost:3001',
        'Accept' => 'application/json',
    ];
}

function inventoryStaff(Library $library, UserRole $role = UserRole::Librarian): User
{
    $user = User::factory()->role($role)->create();
    $user->libraries()->attach($library->id);

    return $user;
}

function inventoryRecord(Library $library, array $attributes = []): InventoryBook
{
    return InventoryBook::create(array_merge([
        'library_id' => $library->id,
        'user_id' => User::factory()->create()->id,
        'status' => InventoryBookStatus::Pending,
        'locale' => 'sr-Latn',
    ], $attributes));
}

test('store creates a pending record and dispatches the generation job', function () {
    Queue::fake();

    $admin = User::factory()->superAdmin()->create();
    $library = Library::factory()->create();

    $response = $this->actingAs($admin)->postJson('/api/v1/inventory-books', [
        'library_id' => $library->id,
    ], inventoryHeaders());

    $response->assertCreated()
        ->assertJsonPath('data.status', InventoryBookStatus::Pending->value)
        ->assertJsonPath('data.library_id', $library->id);

    Queue::assertPushed(GenerateInventoryBookPdf::class);

    expect(InventoryBook::where('library_id', $library->id)->count())->toBe(1);
});

test('store requires an active library for a librarian', function () {
    Queue::fake();

    $library = Library::factory()->create();
    $user = User::factory()->role(UserRole::Librarian)->create();

    $this->actingAs($user)->postJson('/api/v1/inventory-books', [], inventoryHeaders())
        ->assertStatus(422);

    Queue::assertNothingPushed();
});

test('index is scoped to the active library', function () {
    $library = Library::factory()->create();
    $otherLibrary = Library::factory()->create();

    $user = inventoryStaff($library);
    inventoryRecord($library);
    inventoryRecord($otherLibrary);

    $response = $this->actingAs($user)->getJson('/api/v1/inventory-books', inventoryHeaders());

    $response->assertOk()->assertJsonCount(1, 'data');
    expect($response->json('data.0.library_id'))->toBe($library->id);
});

test('a librarian cannot view another library record', function () {
    $library = Library::factory()->create();
    $otherLibrary = Library::factory()->create();
    $user = inventoryStaff($library);
    $record = inventoryRecord($otherLibrary);

    $this->actingAs($user)->getJson("/api/v1/inventory-books/{$record->id}", inventoryHeaders())
        ->assertForbidden();
});

test('download is rejected while the book is not generated', function () {
    $library = Library::factory()->create();
    $user = inventoryStaff($library);
    $record = inventoryRecord($library);

    $this->actingAs($user)->getJson("/api/v1/inventory-books/{$record->id}/download", inventoryHeaders())
        ->assertStatus(409);
});

test('the job generates the pdf and marks the record completed', function () {
    Storage::fake('inventory');

    $library = Library::factory()->create();
    $book = Book::factory()->for($library)->create(['name' => 'Moj naslov']);
    $author = Author::factory()->for($library)->create(['name' => 'Petar Petrović']);
    $book->authors()->attach($author->id);

    BookCopy::factory()->for($library)->for($book)->create([
        'order_number' => '1',
        'seq_number' => 1,
        'binding' => 'tvrdi',
        'udk' => '821.163.41',
    ]);

    $record = inventoryRecord($library);

    (new GenerateInventoryBookPdf($record))->handle(app(InventoryBookPdfService::class));

    $record->refresh();

    expect($record->status)->toBe(InventoryBookStatus::Completed)
        ->and($record->rows_count)->toBe(1)
        ->and($record->file_path)->not->toBeNull();

    Storage::disk('inventory')->assertExists($record->file_path);

    $response = $this->actingAs(inventoryStaff($library))
        ->getJson("/api/v1/inventory-books/{$record->id}/download", inventoryHeaders());

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('application/pdf');
});

test('the job records a failure when generation throws', function () {
    Storage::fake('inventory');

    $library = Library::factory()->create();
    $record = inventoryRecord($library);

    $service = new class extends InventoryBookPdfService
    {
        public function render(Library $library, string $locale): array
        {
            throw new RuntimeException('Generisanje nije uspelo.');
        }
    };

    try {
        (new GenerateInventoryBookPdf($record))->handle($service);
    } catch (Throwable) {
        // Ocekivano: posao baca gresku i status ostaje failed.
    }

    expect($record->refresh()->status)->toBe(InventoryBookStatus::Failed)
        ->and($record->failure_reason)->toContain('Generisanje nije uspelo');
});

test('superadmin without an active library cannot list inventory books', function () {
    $admin = User::factory()->superAdmin()->create();
    $library = Library::factory()->create();
    inventoryRecord($library);

    $this->actingAs($admin)->getJson('/api/v1/inventory-books', inventoryHeaders())
        ->assertStatus(422);
});

test('superadmin sees only inventory books of the active library', function () {
    $admin = User::factory()->superAdmin()->create();
    $library = Library::factory()->create();
    $other = Library::factory()->create();
    $this->withSession(['active_library_id' => $library->id]);

    $own = inventoryRecord($library);
    inventoryRecord($other);

    $this->actingAs($admin)->getJson('/api/v1/inventory-books', inventoryHeaders())
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $own->id);
});
