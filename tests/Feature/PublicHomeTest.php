<?php

test('public home page renders library links', function () {
    $response = $this->get('/');

    $response->assertOk();
    $response->assertSee('Ваша школска библиотека');
    $response->assertSee('на једном месту');
    $response->assertSee(asset('favicon.svg'));
    $response->assertSee(route('libraries.show', 'biblioteka-svetlost', false));
    $response->assertSee(route('libraries.show', 'citaonica-dunav', false));
    $response->assertSee(route('libraries.show', 'gradska-biblioteka', false));
});
