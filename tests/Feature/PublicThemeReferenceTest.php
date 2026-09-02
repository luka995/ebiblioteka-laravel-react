<?php

use App\Mail\ContactMessage;
use Illuminate\Support\Facades\Mail;

test('reference theme home page renders Cyrillic copy and library links', function () {
    $response = $this->get('/?theme=ref');

    $response->assertOk();
    $response->assertSee('Ебиблиотека');
    $response->assertSee('Ваша школска библиотека');
    $response->assertSee(route('libraries.show', 'biblioteka-svetlost'));
    $response->assertSee(route('libraries.show', 'citaonica-dunav'));
    $response->assertSee(route('libraries.show', 'gradska-biblioteka'));
});

test('reference theme catalog page renders Cyrillic copy', function () {
    $response = $this->get(route('libraries.index', ['theme' => 'ref']));

    $response->assertOk();
    $response->assertSee('Каталог књига');
    $response->assertSee('Библиотеке');
    $response->assertSee(route('libraries.show', 'biblioteka-svetlost'));
});

test('reference theme library page renders its categories in Cyrillic', function () {
    $response = $this->get(route('libraries.show', ['library' => 'biblioteka-svetlost', 'theme' => 'ref']));

    $response->assertOk();
    $response->assertSee('Библиотека Светлост');
    $response->assertSee('КАТЕГОРИЈЕ У БИБЛИОТЕЦИ');
    $response->assertSee(route('libraries.category', ['biblioteka-svetlost', 'decje-knjige']));
});

test('reference theme category page lists books in Cyrillic', function () {
    $response = $this->get(route('libraries.category', ['library' => 'biblioteka-svetlost', 'category' => 'lektira', 'theme' => 'ref']));

    $response->assertOk();
    $response->assertSee('Лектира');
    $response->assertSee('Град од папира');
    $response->assertSee(route('libraries.book', ['biblioteka-svetlost', 'lektira', 'grad-od-papira']));
});

test('reference theme book page shows Cyrillic details and reservation call to action', function () {
    $response = $this->get(route('libraries.book', ['library' => 'biblioteka-svetlost', 'category' => 'lektira', 'book' => 'grad-od-papira', 'theme' => 'ref']));

    $response->assertOk();
    $response->assertSee('Град од папира');
    $response->assertSee('Пријавите се за резервацију');
});

test('reference theme project page renders Cyrillic copy', function () {
    $response = $this->get(route('project.about', ['theme' => 'ref']));

    $response->assertOk();
    $response->assertSee('О е-Библиотеци');
    $response->assertSee('Скенирање књига мобилним телефоном');
    $response->assertSee('Подаци који раде за библиотеку');
});

test('reference theme contact page renders Cyrillic copy and contact form', function () {
    $response = $this->get(route('contact.create', ['theme' => 'ref']));

    $response->assertOk();
    $response->assertSee('Контакт');
    $response->assertSee('Име и презиме');
    $response->assertSee(route('contact.store'));
});

test('reference theme contact form sends the message to the academy', function () {
    Mail::fake();

    $response = $this->post(route('contact.store', ['theme' => 'ref']), [
        'name' => 'Мила Јовановић',
        'email' => 'mila@example.com',
        'organization' => 'ОШ Светлост',
        'message' => 'Желели бисмо више информација о еБиблиотеци.',
    ]);

    $response->assertRedirect(route('contact.create'));
    $response->assertSessionHas('contact_sent', true);
    Mail::assertSent(ContactMessage::class, function (ContactMessage $mail) {
        return $mail->hasTo(config('contact.recipient'))
            && $mail->data['name'] === 'Мила Јовановић';
    });
});
