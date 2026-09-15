<?php

use App\Models\Library;

test('public home page renders library links', function () {
    $first = Library::factory()->create(['name' => 'Biblioteka Svetlost']);
    $second = Library::factory()->create(['name' => 'Čitaonica Dunav']);

    $response = $this->get('/');

    $response->assertOk();
    $response->assertSee('Ваша школска библиотека');
    $response->assertSee('на једном месту');
    $response->assertSee(asset('favicon.svg'));
    $response->assertSee(route('libraries.show', $first->slug, false));
    $response->assertSee(route('libraries.show', $second->slug, false));
});
