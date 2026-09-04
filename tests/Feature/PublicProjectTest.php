<?php

test('about project page explains the new ebiblioteka version', function () {
    $response = $this->get(route('project.about'));

    $response->assertOk();
    $response->assertSee(asset('favicon.svg'));
    $response->assertSee('Академија Филиповић');
    $response->assertSee('Скенирање књига мобилним телефоном');
    $response->assertSee('Подаци који раде за библиотеку');
    $response->assertSee(route('libraries.index'));
});
