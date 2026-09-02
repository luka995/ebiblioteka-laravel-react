<?php

namespace App\Http\Controllers;

use App\Support\LibraryCatalog;
use App\Support\PublicTheme;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PublicLibraryController extends Controller
{
    public function index(Request $request): View
    {
        $query = trim((string) $request->query('q', ''));
        $libraries = collect(LibraryCatalog::all())
            ->filter(fn (array $library): bool => $query === '' || str_contains($this->searchable($library), mb_strtolower($query)))
            ->values();

        return view(PublicTheme::view('public.libraries.index'), compact('libraries', 'query'));
    }

    public function show(string $library): View
    {
        $library = $this->findLibrary($library);

        return view(PublicTheme::view('public.libraries.show'), compact('library'));
    }

    public function category(string $library, string $category): View
    {
        $library = $this->findLibrary($library);
        $categoryData = collect($library['categories'])->firstWhere('slug', $category);

        abort_if($categoryData === null, 404);

        return view(PublicTheme::view('public.libraries.category'), [
            'library' => $library,
            'category' => $categoryData,
        ]);
    }

    public function book(string $library, string $category, string $book): View
    {
        $library = $this->findLibrary($library);
        $categoryData = collect($library['categories'])->firstWhere('slug', $category);
        $bookData = $categoryData === null ? null : collect($categoryData['books'])->firstWhere('slug', $book);

        abort_if($categoryData === null || $bookData === null, 404);

        return view(PublicTheme::view('public.libraries.book'), [
            'library' => $library,
            'category' => $categoryData,
            'book' => $bookData,
        ]);
    }

    private function findLibrary(string $slug): array
    {
        $library = collect(LibraryCatalog::all())->firstWhere('slug', $slug);

        abort_if($library === null, 404);

        return $library;
    }

    private function searchable(array $library): string
    {
        return mb_strtolower(implode(' ', [$library['name'], $library['city'], $library['address']]));
    }
}
