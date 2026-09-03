<?php

namespace Database\Seeders;

use App\Models\Place;
use App\Models\Region;
use Illuminate\Database\Seeder;

/**
 * Početne regije i mesta za razvoj (idempotentno).
 */
class LocationSeeder extends Seeder
{
    /**
     * @return array<int, array{name: string, places: array<int, string>}>
     */
    public function regions(): array
    {
        return [
            ['name' => 'Grad Beograd', 'places' => ['Beograd', 'Lazarevac', 'Mladenovac', 'Obrenovac']],
            ['name' => 'Vojvodina', 'places' => ['Novi Sad', 'Subotica', 'Zrenjanin', 'Pančevo', 'Sombor', 'Sremska Mitrovica']],
            ['name' => 'Šumadija i Zapadna Srbija', 'places' => ['Kragujevac', 'Čačak', 'Kraljevo', 'Užice', 'Valjevo', 'Šabac', 'Jagodina']],
            ['name' => 'Južna i Istočna Srbija', 'places' => ['Niš', 'Leskovac', 'Vranje', 'Pirot', 'Zaječar', 'Kruševac', 'Prokuplje']],
            ['name' => 'Kosovo i Metohija', 'places' => ['Priština', 'Prizren', 'Kosovska Mitrovica', 'Peć']],
        ];
    }

    public function run(): void
    {
        foreach ($this->regions() as $entry) {
            $region = Region::firstOrCreate(['name' => $entry['name']]);

            foreach ($entry['places'] as $placeName) {
                Place::firstOrCreate(
                    ['region_id' => $region->id, 'name' => $placeName]
                );
            }
        }
    }
}
