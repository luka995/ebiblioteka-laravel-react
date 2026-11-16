<?php

use App\Mail\PasswordChanged;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

function passwordChangeHeaders(): array
{
    return [
        'Origin' => 'http://localhost:3001',
        'Accept' => 'application/json',
    ];
}

test('admin changing a user password sends the new password by email', function () {
    Mail::fake();

    $admin = User::factory()->superAdmin()->create();
    $user = User::factory()->create();

    $this->actingAs($admin)->putJson("/api/v1/users/{$user->id}/password", [
        'password' => 'new-secret123',
        'password_confirmation' => 'new-secret123',
    ], passwordChangeHeaders())->assertNoContent();

    Mail::assertQueued(PasswordChanged::class, function (PasswordChanged $mail) use ($user) {
        return $mail->hasTo($user->email)
            && $mail->plainPassword === 'new-secret123';
    });
});

test('changing own password via profile does not send the admin password email', function () {
    Mail::fake();

    $user = User::factory()->create();

    $this->actingAs($user)->putJson('/api/v1/profile/password', [
        'current_password' => 'password',
        'password' => 'new-secret123',
        'password_confirmation' => 'new-secret123',
    ], passwordChangeHeaders())->assertNoContent();

    Mail::assertNotQueued(PasswordChanged::class);
});
