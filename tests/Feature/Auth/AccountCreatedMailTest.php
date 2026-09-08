<?php

use App\Mail\AccountCreated;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

test('creating a user sends the account created email with login details', function () {
    Mail::fake();

    $admin = User::factory()->superAdmin()->create();

    $this->actingAs($admin)->postJson('/api/v1/users', [
        'first_name' => 'Petar',
        'last_name' => 'Petrović',
        'username' => 'petar.petrovic',
        'email' => 'petar@example.com',
        'password' => 'secret123',
        'role' => 'user',
    ], [
        'Origin' => 'http://localhost:3001',
        'Accept' => 'application/json',
    ])->assertCreated();

    Mail::assertQueued(AccountCreated::class, function (AccountCreated $mail) {
        return $mail->hasTo('petar@example.com')
            && $mail->plainPassword === 'secret123';
    });
});
