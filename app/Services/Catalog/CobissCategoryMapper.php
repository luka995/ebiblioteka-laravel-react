<?php

namespace App\Services\Catalog;

use App\Models\Category;
use App\Models\Library;
use Database\Seeders\CategorySeeder;

/**
 * Mapira COBISS "vrstu gradje" i UDK broj na postojecu kategoriju biblioteke.
 *
 * Osigurava da osnovno drvo kategorija postoji za svaku ciljnu biblioteku
 * (idempotentno preko `CategorySeeder`), a potom vraca ID najblize
 * kategorije ili null ako nema pouzdanog poklapanja.
 */
class CobissCategoryMapper
{
    /**
     * @var array<int, array<string, int>>
     */
    private array $cache = [];

    public function __construct(private readonly CategorySeeder $seeder) {}

    public function categoryId(Library $library, ?string $category, ?string $udk): ?int
    {
        $map = $this->mapFor($library);

        foreach ($this->matches($category) as $name) {
            if (isset($map[$name])) {
                return $map[$name];
            }
        }

        return $this->fromUdk($map, $udk);
    }

    /**
     * @return array<string, int>
     */
    private function mapFor(Library $library): array
    {
        if (! isset($this->cache[$library->id])) {
            $this->seeder->seedFor($library);

            $this->cache[$library->id] = Category::query()
                ->where('library_id', $library->id)
                ->pluck('id', 'name')
                ->all();
        }

        return $this->cache[$library->id];
    }

    /**
     * Redosled je bitan: specificnija vrsta gradje ima prednost.
     *
     * @return array<int, string>
     */
    private function matches(?string $category): array
    {
        $value = mb_strtolower(trim((string) $category));

        if ($value === '') {
            return [];
        }

        $rules = [
            ['лектира|бајк|дечиј|дечј|млади|основн|сликовниц|др.књиж|др. књиж', 'Лектира'],
            ['поезиј', 'Поезија'],
            ['драм', 'Драма'],
            ['енциклопед|речник|лексикон|приручник', 'Енциклопедије и приручници'],
            ['математ', 'Математика'],
            ['физик|хемиј|биолог|природ|геолог|астроном', 'Природне науке'],
            ['психолог|социолог|прав|економ|филозоф|друштв', 'Друштвене науке'],
            ['историј', 'Историја'],
            ['географ', 'Географија'],
            ['ликовн|сликар', 'Ликовна уметност'],
            ['музик', 'Музика'],
            ['филм', 'Филм'],
            ['уметн', 'Уметност'],
            ['роман|приповет|проза|есеј|књижев', 'Књижевност'],
            ['наук', 'Наука'],
        ];

        $matches = [];

        foreach ($rules as [$pattern, $name]) {
            if (preg_match('/'.$pattern.'/u', $value) === 1) {
                $matches[] = $name;
            }
        }

        return $matches;
    }

    /**
     * @param  array<string, int>  $map
     */
    private function fromUdk(array $map, ?string $udk): ?int
    {
        $udk = trim((string) $udk);

        if ($udk === '') {
            return null;
        }

        $rules = [
            ['821.163.41', ['Домаћа књижевност', 'Књижевност']],
            ['82', ['Светска књижевност', 'Књижевност']],
            ['030', ['Енциклопедије и приручници']],
            ['51', ['Математика', 'Природне науке']],
            ['91', ['Географија']],
            ['9', ['Историја']],
            ['7', ['Уметност']],
            ['5', ['Природне науке']],
            ['3', ['Друштвене науке']],
            ['0', ['Наука']],
        ];

        foreach ($rules as [$prefix, $candidates]) {
            if (! str_starts_with($udk, $prefix)) {
                continue;
            }

            foreach ($candidates as $name) {
                if (isset($map[$name])) {
                    return $map[$name];
                }
            }
        }

        return null;
    }
}
