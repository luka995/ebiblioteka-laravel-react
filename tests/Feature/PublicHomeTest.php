<?php

use App\Models\Library;

test('public home page renders library links and the product sections', function () {
    $first = Library::factory()->create(['name' => 'Biblioteka Svetlost']);
    $second = Library::factory()->create(['name' => 'Čitaonica Dunav']);

    $response = $this->get('/');

    $response->assertOk();
    $response->assertSee('Комплетан софтвер за вашу');
    $response->assertSee('школску библиотеку');
    $response->assertSee('СА еБИБЛИОТЕКОМ');
    $response->assertSee('ДЕМО УЖИВО');
    $response->assertSee('ВОДИЧ ЗА КОРИСНИКЕ');
    $response->assertSee('ЧЕСТО ПОСТАВЉАНА ПИТАЊА');
    $response->assertSee('FAQPage', false);
    $response->assertSee(route('library-requests.create', [], false));
    $response->assertSee(asset('favicon.svg'));
    $response->assertSee(route('libraries.show', $first->slug, false));
    $response->assertSee(route('libraries.show', $second->slug, false));
});

test('public home page does not show the removed book recommendations', function () {
    $this->get('/')
        ->assertOk()
        ->assertDontSee('ОДАБРАНО ЗА ВАС')
        ->assertDontSee('Препорука библиотекара')
        ->assertDontSee('+ још 24 библиотеке ускоро');
});
