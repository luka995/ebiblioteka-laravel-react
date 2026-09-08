<?php

use App\Mail\ContactMessage;
use App\Services\Security\TurnstileService;
use Illuminate\Mail\Mailer;
use Illuminate\Support\Facades\Mail;

function contactPayload(): array
{
    return [
        'name' => 'Mila Jovanović',
        'email' => 'mila@example.com',
        'organization' => 'OŠ Svetlost',
        'message' => 'Želeli bismo više informacija o eBiblioteci.',
        'turnstile_token' => 'valid-turnstile-token',
    ];
}

test('contact page presents academy information and a contact form', function () {
    $response = $this->get(route('contact.create'));

    $response->assertOk();
    $response->assertSee('Академија Филиповић');
    $response->assertSee('Установа или организација');
    $response->assertSee('Ваша порука');
    $response->assertSee(route('contact.store'));
});

test('contact form validates and sends the message to the academy', function () {
    Mail::fake();
    $this->mock(TurnstileService::class, function ($mock) {
        $mock->shouldReceive('verify')->once()->andReturn(true);
    });

    $response = $this->post(route('contact.store'), contactPayload());

    $response->assertRedirect(route('contact.create'));
    $response->assertSessionHas('contact_sent', true);
    Mail::assertQueued(ContactMessage::class, function (ContactMessage $mail) {
        foreach ((array) config('contact.recipient') as $recipient) {
            if (! $mail->hasTo($recipient)) {
                return false;
            }
        }

        return $mail->data['name'] === 'Mila Jovanović'
            && $mail->data['message'] === 'Želeli bismo više informacija o eBiblioteci.';
    });
});

test('contact page renders a success modal after a message is sent', function () {
    $response = $this->withSession(['contact_sent' => true])->get(route('contact.create'));

    $response->assertOk();
    $response->assertSee('role="dialog"', false);
    $response->assertSee('Порука је послата');
    $response->assertSee('Хвала вам што сте нам се јавили.');
});

test('contact page renders an error modal when a sending failure is flashed', function () {
    $response = $this->withSession(['contact_error' => 'Дошло је до грешке приликом слања поруке. Покушајте поново.'])
        ->get(route('contact.create'));

    $response->assertOk();
    $response->assertSee('role="dialog"', false);
    $response->assertSee('Дошло је до грешке');
    $response->assertSee('Дошло је до грешке приликом слања поруке. Покушајте поново.');
});

test('contact form flashes an error when the mailer fails', function () {
    $this->mock(TurnstileService::class, function ($mock) {
        $mock->shouldReceive('verify')->once()->andReturn(true);
    });

    $mailer = Mockery::mock(Mailer::class);
    $mailer->shouldReceive('to')->once()->andReturnSelf();
    $mailer->shouldReceive('send')->once()->andThrow(new RuntimeException('SMTP unreachable'));

    Mail::shouldReceive('mailer')->once()->andReturn($mailer);

    $response = $this->post(route('contact.store'), contactPayload());

    $response->assertRedirect(route('contact.create'));
    $response->assertSessionHas('contact_error');
    $response->assertSessionMissing('contact_sent');
});

test('contact form requires a name, valid email address, and message', function () {
    $response = $this->from(route('contact.create'))->post(route('contact.store'), [
        'name' => '',
        'email' => 'not-an-email',
        'message' => '',
    ]);

    $response->assertRedirect(route('contact.create'));
    $response->assertSessionHasErrors(['name', 'email', 'message']);
});

test('contact form rejects an invalid Turnstile token', function () {
    Mail::fake();
    $this->mock(TurnstileService::class, function ($mock) {
        $mock->shouldReceive('verify')->once()->andReturn(false);
    });

    $response = $this->post(route('contact.store'), contactPayload());

    $response->assertRedirect(route('contact.create'));
    $response->assertSessionHas('contact_error');
    $response->assertSessionMissing('contact_sent');
    Mail::assertNothingQueued();
});

test('contact form rejects a request without a Turnstile token', function () {
    Mail::fake();

    $payload = contactPayload();
    unset($payload['turnstile_token']);

    $response = $this->post(route('contact.store'), $payload);

    $response->assertRedirect(route('contact.create'));
    $response->assertSessionHas('contact_error');
    $response->assertSessionMissing('contact_sent');
    Mail::assertNothingQueued();
});

test('contact form rejects a request when Cloudflare reports failure', function () {
    Mail::fake();
    $this->mock(TurnstileService::class, function ($mock) {
        $mock->shouldReceive('verify')->once()->andReturn(false);
    });

    $response = $this->post(route('contact.store'), contactPayload());

    $response->assertRedirect(route('contact.create'));
    $response->assertSessionHas('contact_error');
    $response->assertSessionMissing('contact_sent');
    Mail::assertNothingQueued();
});
