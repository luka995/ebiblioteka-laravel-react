<?php

namespace App\Services\Mail;

use App\Mail\AccountCreated;
use App\Mail\ResetPassword;
use App\Models\User;
use App\Support\FrontendUrl;

class UserMailService
{
    public function __construct(
        private readonly TransactionalMailService $mailer,
    ) {}

    public function sendPasswordReset(User $user, string $token): void
    {
        $resetUrl = FrontendUrl::url().'/reset-password?'.http_build_query([
            'token' => $token,
            'email' => $user->getEmailForPasswordReset(),
        ]);

        $this->mailer->send(new ResetPassword($user, $resetUrl));
    }

    public function sendAccountCreated(User $user, string $plainPassword): void
    {
        $this->mailer->send(
            new AccountCreated($user, $plainPassword, FrontendUrl::url().'/login')
        );
    }
}
