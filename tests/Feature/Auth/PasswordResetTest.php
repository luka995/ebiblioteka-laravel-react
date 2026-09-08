<?php

use App\Mail\ResetPassword;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

function statefulHeaders(): array
{
    return [
        'Origin' => 'http://localhost:3001',
        'Accept' => 'application/json',
    ];
}

test('reset password link can be requested', function () {
    Mail::fake();

    $user = User::factory()->create();

    $this->post('/api/v1/auth/forgot-password', ['email' => $user->email], statefulHeaders());

    Mail::assertQueued(ResetPassword::class, fn (ResetPassword $mail) => $mail->hasTo($user->email));
});

test('password can be reset with valid token', function () {
    Mail::fake();

    $user = User::factory()->create();

    $this->post('/api/v1/auth/forgot-password', ['email' => $user->email], statefulHeaders());

    Mail::assertQueued(ResetPassword::class, function (ResetPassword $mail) use ($user) {
        parse_str((string) parse_url($mail->resetUrl, PHP_URL_QUERY), $query);

        $response = $this->post('/api/v1/auth/reset-password', [
            'token' => $query['token'],
            'email' => $user->email,
            'password' => 'password',
            'password_confirmation' => 'password',
        ], statefulHeaders());

        $response
            ->assertSessionHasNoErrors()
            ->assertStatus(200);

        return true;
    });
});
