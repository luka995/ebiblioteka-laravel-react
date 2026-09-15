<?php

namespace App\Console\Commands;

use App\Services\Catalog\CobissHarvestService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Jednokratno prikupljanje realnih bibliografskih zapisa iz COBISS+ legacy.
 *
 * Rezultat se cuva u JSON fixture koji `CobissBookSeeder` cita bez mreze.
 * Komanda je resumable: ako fixture postoji, prethodni zapisi se zadrzavaju,
 * a prikupljaju se samo novi do zadatog cilja.
 */
class HarvestCobissCatalog extends Command
{
    protected $signature = 'cobiss:harvest
        {--target= : Broj jedinstvenih zapisa koje treba prikupiti}
        {--queries= : Zarezom razdvojeni upiti (podrazumevano iz config/cobiss.php)}
        {--out= : Putanja do JSON fixture-a}
        {--fresh : Ignorisi postojeci fixture i kreni od nule}';

    protected $description = 'Prikuplja realne zapise iz COBISS+ kataloga u lokalni JSON fixture';

    public function handle(CobissHarvestService $harvester): int
    {
        if (! (bool) config('isbn.nbs.enabled')) {
            $this->components->error('COBISS izvor je iskljucen (NBS_CATALOG_ENABLED=false).');

            return self::FAILURE;
        }

        $target = max(1, (int) ($this->option('target') ?: config('cobiss.target', 750)));
        $queries = $this->queries();
        $path = (string) ($this->option('out') ?: config('cobiss.fixture_path'));

        $existing = $this->option('fresh') ? [] : $this->readExisting($path);

        if ($existing !== []) {
            $this->components->info(sprintf('Nastavljam od %d postojecih zapisa.', count($existing)));
        }

        $this->components->info(sprintf(
            'Harvest: cilj %d zapisa, %d upita, Crawl-delay %.1fs.',
            $target,
            count($queries),
            (float) config('cobiss.crawl_delay', 1),
        ));

        $records = $harvester->harvest(
            $target,
            $queries,
            $existing,
            function (string $label, int $current, int $total): void {
                if ($current <= 1 || $current % 10 === 0 || $current >= $total) {
                    $this->output->write(sprintf("\r  [%d/%d] %s", $current, $total, mb_substr($label, 0, 60)).str_repeat(' ', 12));
                }
            },
        );

        $this->newLine(2);

        $this->writeFixture($path, $records);

        $publishers = collect($records)->pluck('publisher')->filter()->unique()->count();

        $this->components->info(sprintf(
            'Sacuvano %d zapisa (%d jedinstvenih izdavaca) u %s',
            count($records),
            $publishers,
            $path,
        ));

        return self::SUCCESS;
    }

    /**
     * @return array<int, string>
     */
    private function queries(): array
    {
        $option = $this->option('queries');

        if (is_string($option) && trim($option) !== '') {
            return array_values(array_filter(array_map('trim', explode(',', $option))));
        }

        return array_values((array) config('cobiss.queries', []));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function readExisting(string $path): array
    {
        if (! File::exists($path)) {
            return [];
        }

        $data = json_decode((string) File::get($path), true);

        if (! is_array($data)) {
            return [];
        }

        $records = $data['records'] ?? $data;

        return is_array($records) ? array_values($records) : [];
    }

    /**
     * @param  array<int, array<string, mixed>>  $records
     */
    private function writeFixture(string $path, array $records): void
    {
        File::ensureDirectoryExists(dirname($path));

        $payload = [
            'source' => 'cobiss',
            'harvested_at' => now()->toIso8601String(),
            'count' => count($records),
            'records' => array_values($records),
        ];

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

        $temporary = $path.'.tmp';
        File::put($temporary, $json."\n");
        File::move($temporary, $path);
    }
}
