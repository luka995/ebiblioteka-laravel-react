<?php

namespace App\Http\Controllers;

use App\Http\Resources\BookHistoryResource;
use App\Models\BookCopy;
use App\Models\BookHistory;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BookHistoryController extends Controller
{
    /**
     * Audit istorija konkretne fizicke jedinice.
     *
     * @return AnonymousResourceCollection<int, BookHistoryResource>
     */
    public function forCopy(BookCopy $bookCopy): AnonymousResourceCollection
    {
        $entries = BookHistory::query()
            ->with(['library', 'book', 'bookCopy', 'user', 'actor'])
            ->where('book_copy_id', $bookCopy->id)
            ->orderByDesc('action_date')
            ->orderByDesc('id')
            ->limit(200)
            ->get();

        return BookHistoryResource::collection($entries);
    }
}
