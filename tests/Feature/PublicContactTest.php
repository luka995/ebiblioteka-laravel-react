<?php

use App\Mail\ContactMessage;
use Illuminate\Support\Facades\Mail;

test('contact page presents academy information and a contact form', function () {
    $response = $this->get(route('contact.create'));

    $response->assertOk();
    $response->assertSee('Akademija Filipović');
    $response->assertSee('Ustanova ili organizacija');
    $response->assertSee('Vaša poruka');
    $response->assertSee(route('contact.store'));
});

test('contact form validates and sends the message to the academy', function () {
    Mail::fake();

    $response = $this->post(route('contact.store'), [
        'name' => 'Mila Jovanović',
        'email' => 'mila@example.com',
        'organization' => 'OŠ Svetlost',
        'message' => 'Želeli bismo više informacija o eBiblioteci.',
    ]);

    $response->assertRedirect(route('contact.create'));
    $response->assertSessionHas('contact_sent', true);
    Mail::assertSent(ContactMessage::class, function (ContactMessage $mail) {
        return $mail->hasTo(config('contact.recipient'))
            && $mail->data['name'] === 'Mila Jovanović'
            && $mail->data['message'] === 'Želeli bismo više informacija o eBiblioteci.';
    });
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
