<?php

use App\Enums\LibraryRequestStatus;
use App\Models\LibraryRequest;
use App\Models\Place;
use App\Models\User;

function libraryRequestsHeaders(): array
{
    return [
        'Origin' => 'http://localhost:3001',
        'Accept' => 'application/json',
    ];
}

function makeLibraryRequest(array $overrides = []): LibraryRequest
{
    return LibraryRequest::create(array_merge([
        'school_name' => 'ОШ Вук Караџић',
        'school_type' => 'osnovna',
        'city' => 'Београд',
        'address' => 'Краља Милана 1',
        'contact_name' => 'Ана Јовановић',
        'contact_email' => 'ana@example.com',
        'contact_phone' => '0638249999',
        'student_count' => 620,
        'employee_count' => 48,
        'message' => 'Желимо библиотеку.',
        'status' => LibraryRequestStatus::Pending,
    ], $overrides));
}

test('superadmin can list and open library requests', function () {
    $admin = User::factory()->superAdmin()->create();
    $first = makeLibraryRequest(['school_name' => 'ОШ Прва']);
    $second = makeLibraryRequest(['school_name' => 'Гимназија Друга', 'city' => 'Ниш']);

    $this->actingAs($admin)
        ->getJson('/api/v1/library-requests', libraryRequestsHeaders())
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', $second->id)
        ->assertJsonPath('data.1.id', $first->id);

    $this->actingAs($admin)
        ->getJson('/api/v1/library-requests/'.$first->id, libraryRequestsHeaders())
        ->assertOk()
        ->assertJsonPath('data.school_name', 'ОШ Прва');
});

test('regular users cannot access library requests', function () {
    $user = User::factory()->create();
    $request = makeLibraryRequest();

    $this->actingAs($user)
        ->getJson('/api/v1/library-requests', libraryRequestsHeaders())
        ->assertForbidden();

    $this->actingAs($user)
        ->getJson('/api/v1/library-requests/'.$request->id, libraryRequestsHeaders())
        ->assertForbidden();

    $this->actingAs($user)
        ->postJson('/api/v1/library-requests/'.$request->id.'/approve', [], libraryRequestsHeaders())
        ->assertForbidden();
});

test('superadmin can create a library from a request', function () {
    $admin = User::factory()->superAdmin()->create();
    $place = Place::factory()->create();
    $request = makeLibraryRequest();

    $response = $this->actingAs($admin)
        ->postJson('/api/v1/library-requests/'.$request->id.'/approve', [
            'name' => 'ОШ Вук Караџић',
            'address' => 'Краља Милана 1',
            'place_id' => $place->id,
            'work_time' => 'Пон - Пет, 08:00 - 16:00',
            'inv_number_auto' => true,
        ], libraryRequestsHeaders());

    $response->assertCreated()
        ->assertJsonPath('data.name', 'ОШ Вук Караџић')
        ->assertJsonPath('data.place_id', $place->id);

    $this->assertDatabaseHas('libraries', [
        'name' => 'ОШ Вук Караџић',
        'address' => 'Краља Милана 1',
        'place_id' => $place->id,
    ]);
    $this->assertDatabaseHas('library_requests', [
        'id' => $request->id,
        'status' => LibraryRequestStatus::Approved->value,
        'handled_by_id' => $admin->id,
    ]);
    expect($request->fresh()->handled_at)->not->toBeNull();
});

test('an already processed request cannot be approved twice', function () {
    $admin = User::factory()->superAdmin()->create();
    $place = Place::factory()->create();
    $request = makeLibraryRequest(['status' => LibraryRequestStatus::Approved]);

    $this->actingAs($admin)
        ->postJson('/api/v1/library-requests/'.$request->id.'/approve', [
            'name' => 'ОШ Вук Караџић',
            'address' => 'Краља Милана 1',
            'place_id' => $place->id,
        ], libraryRequestsHeaders())
        ->assertForbidden();
});

test('superadmin can reject a request', function () {
    $admin = User::factory()->superAdmin()->create();
    $request = makeLibraryRequest();

    $this->actingAs($admin)
        ->postJson('/api/v1/library-requests/'.$request->id.'/reject', [], libraryRequestsHeaders())
        ->assertOk()
        ->assertJsonPath('data.status', LibraryRequestStatus::Rejected->value)
        ->assertJsonPath('data.can.approve', false);

    $this->assertDatabaseHas('library_requests', [
        'id' => $request->id,
        'status' => LibraryRequestStatus::Rejected->value,
        'handled_by_id' => $admin->id,
    ]);
});
