<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Library;
use Illuminate\Database\Seeder;

/**
 * Развојне школске категорије за библиотеку Академија Filipovic.
 *
 * Seeder је идемпотентан и не мења постојеће ручно унете категорије.
 */
class CategorySeeder extends Seeder
{
    private const LIBRARY_NAME = 'Akademija Filipovic';

    /**
     * @return array<int, array{name: string, children?: array<int, string>}>
     */
    public function categories(): array
    {
        return [
            [
                'name' => 'Лектира',
                'children' => [
                    'Лектира за ниже разреде',
                    'Лектира за више разреде',
                    'Домаћа лектира',
                    'Страна лектира',
                ],
            ],
            [
                'name' => 'Књижевност',
                'children' => [
                    'Домаћа књижевност',
                    'Светска књижевност',
                    'Поезија',
                    'Драма',
                ],
            ],
            [
                'name' => 'Наука',
                'children' => [
                    'Математика',
                    'Природне науке',
                    'Друштвене науке',
                ],
            ],
            [
                'name' => 'Историја',
                'children' => [
                    'Национална историја',
                    'Светска историја',
                ],
            ],
            [
                'name' => 'Географија',
                'children' => [
                    'Географија Србије',
                    'Светска географија',
                ],
            ],
            [
                'name' => 'Уметност',
                'children' => [
                    'Ликовна уметност',
                    'Музика',
                    'Филм',
                ],
            ],
            ['name' => 'Енциклопедије и приручници'],
        ];
    }

    public function run(): void
    {
        $library = Library::query()
            ->where('name', self::LIBRARY_NAME)
            ->where('deleted', false)
            ->firstOrFail();

        $this->seedFor($library);
    }

    /**
     * Osigurava osnovno drvo kategorija za datu biblioteku (idempotentno).
     */
    public function seedFor(Library $library): void
    {
        foreach ($this->categories() as $definition) {
            $parent = Category::firstOrCreate([
                'library_id' => $library->id,
                'name' => $definition['name'],
                'parent_id' => null,
            ]);

            foreach ($definition['children'] ?? [] as $childName) {
                Category::firstOrCreate([
                    'library_id' => $library->id,
                    'name' => $childName,
                    'parent_id' => $parent->id,
                ]);
            }
        }
    }
}
