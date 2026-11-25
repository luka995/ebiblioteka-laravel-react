<?php

namespace App\Services;

use App\Models\Author;
use App\Support\Text;

/**
 * Pronalazi postojeceg autora u biblioteci bez obzira na format unosa.
 *
 * Autori mogu stici kao „Ivo Andric“, „Andric, Ivo“ ili „Иво Андрић“, pa se
 * poredjenje vrsi preko kanonskog kljuca umesto direktnog poredjenja stringa.
 */
class AuthorResolverService
{
    /** @var array<int, array<string, Author>> */
    private array $cache = [];

    public function key(string $name): string
    {
        return Text::authorKey($name);
    }

    public function findInLibrary(int $libraryId, string $name): ?Author
    {
        $key = $this->key($name);

        if ($key === '') {
            return null;
        }

        return $this->authorsFor($libraryId)[$key] ?? null;
    }

    public function findOrCreate(int $libraryId, string $name): Author
    {
        $name = trim($name);
        $existing = $this->findInLibrary($libraryId, $name);

        if ($existing instanceof Author) {
            return $existing;
        }

        $author = Author::firstOrCreate([
            'library_id' => $libraryId,
            'name' => $name,
        ]);

        $this->cache[$libraryId][$this->key($author->name)] = $author;

        return $author;
    }

    /**
     * @return array<string, Author>
     */
    private function authorsFor(int $libraryId): array
    {
        if (! array_key_exists($libraryId, $this->cache)) {
            $this->cache[$libraryId] = Author::query()
                ->where('library_id', $libraryId)
                ->get()
                ->keyBy(fn (Author $author): string => $this->key($author->name))
                ->all();
        }

        return $this->cache[$libraryId];
    }
}
