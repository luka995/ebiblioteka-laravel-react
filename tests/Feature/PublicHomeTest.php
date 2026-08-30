<?php

test('public home page renders library links', function () {
    $response = $this->get('/');

    $response->assertOk();
    $response->assertSee(asset('favicon.svg'));
    $response->assertSee(route('libraries.show', 'biblioteka-svetlost'));
    $response->assertSee(route('libraries.show', 'citaonica-dunav'));
    $response->assertSee(route('libraries.show', 'gradska-biblioteka'));
});
