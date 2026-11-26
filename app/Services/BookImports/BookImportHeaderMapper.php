<?php

namespace App\Services\BookImports;

use App\DTOs\BookImportHeaderMapDTO;
use App\Enums\BookImportFailureEnum;
use App\Exceptions\BookImportFileException;
use App\Support\Text;

class BookImportHeaderMapper
{
    private const ALIASES = [
        'isbn' => ['isbn', 'isbn broj', 'isbn number', 'međunarodni broj'],
        'title' => ['naslov', 'naziv', 'naziv knjige', 'title', 'book title'],
        'authors' => ['autor', 'autori', 'author', 'authors'],
        'category' => ['primarna kategorija', 'kategorija', 'kategorije', 'oblast'],
        'category_secondary' => ['sekundarna kategorija', 'druga kategorija', 'secondary category'],
        'description' => ['opis', 'opis knjige', 'description'],
        'cover_url' => ['url korice', 'url korica', 'cover url'],
        'inventory_number' => ['inventarni broj', 'inv broj', 'inv br', 'inventory number', 'order number', 'broj inventara'],
        'seq_number' => ['redni broj', 'seq number'],
        'publisher' => ['izdavac', 'publisher'],
        'publish_place' => ['mesto izdanja', 'mesto', 'publish place'],
        'publish_year' => ['godina', 'godina izdanja', 'publish year'],
        'issue_number' => ['broj izdanja', 'issue number'],
        'num_of_pages' => ['broj strana', 'strana', 'pages'],
        'dimension' => ['dimenzije', 'format', 'dimensions'],
        'part' => ['tom', 'part'],
        'udk' => ['udk', 'signatura', 'signature'],
        'binding' => ['povez', 'povez knjige', 'binding'],
        'origin' => ['tip nabavke', 'način nabavke', 'origin'],
        'book_number' => ['broj knjige', 'book number'],
        'place_on_shelf' => ['mesto na polici', 'polica', 'place on shelf'],
        'price' => ['cena', 'price'],
        'notice' => ['napomena', 'notice'],
    ];

    /** @var array<string, string> */
    private array $aliases = [];

    public function __construct()
    {
        foreach (self::ALIASES as $field => $aliases) {
            foreach ($aliases as $alias) {
                $this->aliases[Text::labelKey($alias)] = $field;
            }
        }
    }

    /**
     * @param  array<int, string|null>  $headers
     */
    public function map(array $headers, int $rowNumber, string $sheetName): ?BookImportHeaderMapDTO
    {
        $columns = [];
        $duplicate = false;

        foreach ($headers as $index => $header) {
            $field = $this->aliases[Text::labelKey($header)] ?? null;

            if ($field !== null) {
                $duplicate = $duplicate || isset($columns[$field]);
                $columns[$field] = $index;
            }
        }

        if (! isset($columns['isbn']) && ! isset($columns['title'])) {
            return null;
        }

        if ($duplicate) {
            throw new BookImportFileException(BookImportFailureEnum::DuplicateHeaders);
        }

        return new BookImportHeaderMapDTO($sheetName, $rowNumber, $headers, $columns);
    }
}
