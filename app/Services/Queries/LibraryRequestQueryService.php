<?php

namespace App\Services\Queries;

use App\DTOs\Queries\LibraryRequestListQueryDTO;
use App\Enums\LibraryRequestStatus;
use App\Models\LibraryRequest;
use App\Queries\Concerns\AppliesTransliteratedSearch;
use App\Services\Sorting\QuerySortBuilder;
use App\Services\Sorting\SortParser;
use App\Services\Sorting\SortRule;
use Illuminate\Database\Eloquent\Builder;

final class LibraryRequestQueryService
{
    use AppliesTransliteratedSearch;

    public function __construct(private readonly SortParser $sortParser) {}

    /**
     * @return Builder<LibraryRequest>
     */
    public function build(LibraryRequestListQueryDTO $query): Builder
    {
        $builder = LibraryRequest::query()
            ->with('handledBy')
            ->when(
                in_array($query->status, LibraryRequestStatus::values(), true),
                fn (Builder $builder) => $builder->where('status', $query->status)
            );

        if ($query->search !== null && trim($query->search) !== '') {
            $term = trim($query->search);

            $builder->where(function (Builder $builder) use ($term): void {
                $this->whereTransliterated($builder, 'school_name', $term);
                $this->whereTransliterated($builder, 'city', $term, 'or');
                $this->whereTransliterated($builder, 'contact_name', $term, 'or');
                $this->whereTransliterated($builder, 'contact_email', $term, 'or');
            });
        }

        $sort = $this->sortParser->parse($query->sorting->sort, $query->sorting->direction);

        return (new QuerySortBuilder)
            ->allow([
                'school' => 'library_requests.school_name',
                'city' => 'library_requests.city',
                'contact' => 'library_requests.contact_name',
                'created_at' => 'library_requests.created_at',
                'status' => 'library_requests.status',
            ])
            ->defaults(new SortRule('created_at', 'desc'))
            ->apply($builder, $sort);
    }
}
