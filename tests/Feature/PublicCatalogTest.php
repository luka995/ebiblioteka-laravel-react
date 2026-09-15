<?php

use App\Models\Author;
use App\Models\Book;
use App\Models\BookCopy;
use App\Models\Category;
use App\Models\Library;

function makePublicCatalog(): array
{
    $library = Library::factory()->create(['name' => 'Народна библиотека']);
    $category = Category::factory()->for($library)->create(['name' => 'Лектира']);
    $author = Author::factory()->for($library)->create(['name' => 'Иво Андрић']);
    $book = Book::factory()->for($library)->create([
        'name' => 'На Дрини ћуприја',
        'category_primary_id' => $category->id,
        'description' => 'Роман о мосту и људима.',
    ]);
    $book->authors()->attach($author->id);

    BookCopy::factory()->for($library)->for($book)->create(['order_number' => '1']);

    return [$library, $category, $book];
}

test('public library catalog renders real libraries and categories', function () {
    [$library, $category] = makePublicCatalog();

    $this->get('/biblioteke')
        ->assertOk()
        ->assertSee('Народна библиотека');

    $this->get('/biblioteke/'.$library->slug)
        ->assertOk()
        ->assertSee('Лектира');
});

test('public category and book pages render real titles and availability', function () {
    [$library, $category, $book] = makePublicCatalog();

    $this->get('/biblioteke/'.$library->slug.'/kategorija/'.$category->slug)
        ->assertOk()
        ->assertSee('На Дрини ћуприја');

    $this->get('/biblioteke/'.$library->slug.'/kategorija/'.$category->slug.'/knjiga/'.$book->slug)
        ->assertOk()
        ->assertSee('На Дрини ћуприја')
        ->assertSee('Доступно');
});

test('deleted libraries and the books of other libraries are not exposed', function () {
    $visible = Library::factory()->create(['name' => 'Видљива библиотека']);
    $hidden = Library::factory()->create(['name' => 'Скривена библиотека', 'deleted' => true]);

    $this->get('/biblioteke')->assertOk()->assertSee('Видљива библиотека')->assertDontSee('Скривена библиотека');

    $this->get('/biblioteke/'.$hidden->slug)->assertNotFound();
});
