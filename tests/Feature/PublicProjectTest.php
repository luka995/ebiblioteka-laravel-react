<?php

test('about project page explains the new ebiblioteka version', function () {
    $response = $this->get(route('project.about'));

    $response->assertOk();
    $response->assertSee(asset('favicon.svg'));
    $response->assertSee('Akademija Filipović');
    $response->assertSee('Skeniranje knjiga mobilnim telefonom');
    $response->assertSee('Podaci koji rade za biblioteku');
    $response->assertSee(route('libraries.index'));
});
