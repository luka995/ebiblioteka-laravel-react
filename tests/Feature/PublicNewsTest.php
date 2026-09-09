<?php

use App\Models\News;

test('public home page renders latest news', function () {
    News::factory()->create(['title' => 'Најновија вест', 'date' => '2026-09-09']);
    News::factory()->create(['title' => 'Старија вест', 'date' => '2026-09-01']);

    $this->get('/')
        ->assertOk()
        ->assertSee('Најновија вест');
});

test('public news detail page renders the html body', function () {
    $news = News::factory()->create([
        'title' => 'Детаљна вест',
        'slug' => 'detaljna-vest',
        'body' => '<p>Први пасус.</p><h3>Поднаслов</h3><ul><li>Ставка</li></ul>',
    ]);

    $this->get('/novosti/detaljna-vest')
        ->assertOk()
        ->assertSee('Детаљна вест')
        ->assertSee('Први пасус.')
        ->assertSee('Поднаслов');
});

test('public news detail page returns 404 for unknown slug', function () {
    $this->get('/novosti/nepostojeca-vest')
        ->assertNotFound();
});
