<?php

use App\Mail\LibraryRequestReceived;
use App\Models\LibraryRequest;
use App\Models\User;
use App\Services\Security\TurnstileService;
use Illuminate\Support\Facades\Mail;

function libraryRequestPayload(): array
{
    return [
        'school_name' => 'ОШ Вук Караџић',
        'school_type' => 'osnovna',
        'city' => 'Београд',
        'address' => 'Краља Милана 1',
        'contact_name' => 'Ана Јовановић',
        'contact_email' => 'ana@example.com',
        'contact_phone' => '0638249999',
        'student_count' => 620,
        'employee_count' => 48,
        'message' => 'Желимо да отворимо библиотеку за нашу школу.',
        'turnstile_token' => 'valid-turnstile-token',
    ];
}

test('school registration page renders the request form', function () {
    $response = $this->get(route('library-requests.create'));

    $response->assertOk();
    $response->assertSee('Регистрација школе');
    $response->assertSee('Назив школе');
    $response->assertSee('Тип школе');
    $response->assertSee(route('library-requests.store', [], false));
});

test('school registration stores the request and notifies all superadmins', function () {
    Mail::fake();
    $first = User::factory()->superAdmin()->create();
    $second = User::factory()->superAdmin()->create();

    $this->mock(TurnstileService::class, function ($mock) {
        $mock->shouldReceive('verify')->once()->andReturn(true);
    });

    $response = $this->post(route('library-requests.store'), libraryRequestPayload());

    $response->assertRedirect(route('library-requests.create'));
    $response->assertSessionHas('library_request_sent', true);
    $this->assertDatabaseHas('library_requests', [
        'school_name' => 'ОШ Вук Караџић',
        'school_type' => 'osnovna',
        'contact_email' => 'ana@example.com',
        'status' => 'pending',
    ]);

    Mail::assertQueued(LibraryRequestReceived::class, function (LibraryRequestReceived $mail) use ($first, $second) {
        return $mail->hasTo($first->email)
            && $mail->hasTo($second->email)
            && $mail->libraryRequest->school_name === 'ОШ Вук Караџић';
    });
});

test('school registration falls back to the contact recipient when no superadmin exists', function () {
    Mail::fake();
    config(['contact.recipient' => ['fallback@example.com']]);

    $this->mock(TurnstileService::class, function ($mock) {
        $mock->shouldReceive('verify')->once()->andReturn(true);
    });

    $this->post(route('library-requests.store'), libraryRequestPayload())
        ->assertRedirect(route('library-requests.create'));

    Mail::assertQueued(LibraryRequestReceived::class, fn (LibraryRequestReceived $mail) => $mail->hasTo('fallback@example.com'));
});

test('school registration rejects an invalid Turnstile token and stores nothing', function () {
    Mail::fake();

    $this->mock(TurnstileService::class, function ($mock) {
        $mock->shouldReceive('verify')->once()->andReturn(false);
    });

    $response = $this->post(route('library-requests.store'), libraryRequestPayload());

    $response->assertRedirect(route('library-requests.create'));
    $response->assertSessionHas('library_request_error');
    $response->assertSessionMissing('library_request_sent');
    expect(LibraryRequest::query()->count())->toBe(0);
    Mail::assertNothingQueued();
});

test('school registration validates the required school and contact data', function () {
    $response = $this->from(route('library-requests.create'))->post(route('library-requests.store'), [
        'school_name' => '',
        'school_type' => 'nepostojeci',
        'city' => '',
        'address' => '',
        'contact_name' => '',
        'contact_email' => 'not-an-email',
        'contact_phone' => '',
    ]);

    $response->assertRedirect(route('library-requests.create'));
    $response->assertSessionHasErrors(['school_name', 'school_type', 'city', 'address', 'contact_name', 'contact_email', 'contact_phone']);
});

test('school registration page renders a success modal after sending', function () {
    $response = $this->withSession(['library_request_sent' => true])->get(route('library-requests.create'));

    $response->assertOk();
    $response->assertSee('role="dialog"', false);
    $response->assertSee('Захтев је послат');
    $response->assertSee('Академија Филиповић ће вас контактирати');
});
