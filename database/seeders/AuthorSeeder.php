<?php

namespace Database\Seeders;

use App\Models\Author;
use App\Models\Library;
use Illuminate\Database\Seeder;

/**
 * Развојни аутори школског фонда за библиотеку Академија Filipovic.
 *
 * Seeder је идемпотентан и не прави дупликате унутар библиотеке.
 */
class AuthorSeeder extends Seeder
{
    private const LIBRARY_NAME = 'Akademija Filipovic';

    /**
     * @return array<int, string>
     */
    private function authors(): array
    {
        return [
            'Иво Андрић',
            'Милош Црњански',
            'Бранко Ћопић',
            'Десанка Максимовић',
            'Меша Селимовић',
            'Добрица Ћосић',
            'Милорад Павић',
            'Данило Киш',
            'Борисав Станковић',
            'Бранислав Нушић',
            'Јован Јовановић Змај',
            'Васко Попа',
            'Исидора Секулић',
            'Јован Дучић',
            'Алекса Шантић',
            'Петар II Петровић Његош',
            'Стеван Сремац',
            'Радоје Домановић',
            'Светлана Велмар-Јанковић',
            'Гроздана Олујић',
        ];
    }

    public function run(): void
    {
        $library = Library::query()
            ->where('name', self::LIBRARY_NAME)
            ->where('deleted', false)
            ->firstOrFail();

        foreach ($this->authors() as $name) {
            Author::firstOrCreate([
                'library_id' => $library->id,
                'name' => $name,
            ]);
        }
    }
}
