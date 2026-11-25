<?php

namespace App\Services\Queries;

use App\DTOs\Queries\UserListQueryDTO;
use App\Models\User;
use App\Queries\UserFilters;
use App\Services\ActiveLibraryService;
use App\Services\Sorting\QuerySortBuilder;
use App\Services\Sorting\SortParser;
use App\Services\Sorting\SortRule;
use Illuminate\Database\Eloquent\Builder;

final class UserQueryService
{
    public function __construct(
        private readonly SortParser $sortParser,
        private readonly ActiveLibraryService $activeLibrary,
    ) {}

    /**
     * @return Builder<User>
     */
    public function build(UserListQueryDTO $query, User $actor): Builder
    {
        $filters = array_filter([
            'email' => $query->email,
            'username' => $query->username,
            'first_name' => $query->firstName,
            'last_name' => $query->lastName,
            'bar_code' => $query->barCode,
            'jmbg' => $query->jmbg,
            'city' => $query->city,
            'role' => $query->role,
            'library_id' => $query->libraryId,
        ], fn (mixed $value): bool => $value !== null && $value !== '');

        if (! $actor->isSuperAdmin()) {
            unset($filters['library_id']);
        }

        if (! empty($filters['role'])) {
            $active = $this->activeLibrary->resolve($actor);
            $roleLibraryId = ! empty($filters['library_id'])
                ? (int) $filters['library_id']
                : $active?->id;

            if ($roleLibraryId !== null) {
                $filters['role_library_id'] = $roleLibraryId;
            }
        }

        $builder = (new UserFilters)->apply(
            User::query()->with(['libraries', 'librariesWithTrashed']),
            $filters
        );

        if ($actor->isSuperAdmin()) {
            if (empty($filters['library_id'])) {
                $builder = $this->activeLibrary->scopeUsers($builder, $actor);
            }
        } else {
            $library = $this->activeLibrary->requireActiveLibrary($actor);
            $builder->whereHas('libraries', fn (Builder $membership) => $membership->whereKey($library->id));
        }

        $sort = $this->sortParser->parse($query->sorting->sort, $query->sorting->direction);

        return (new QuerySortBuilder)
            ->allow([
                'user' => 'users.name',
                'email' => 'users.email',
                'role' => fn (Builder $query, string $direction) => $query->orderByRaw(
                    '(select library_user.role from library_user where library_user.user_id = users.id and library_user.deleted_at is null order by library_user.library_id limit 1) '.$direction
                ),
                'libraries' => fn (Builder $query, string $direction) => $query->orderByRaw(
                    '(select min(libraries.name) from libraries join library_user on library_user.library_id = libraries.id where library_user.user_id = users.id and library_user.deleted_at is null) '.$direction
                ),
                'bar_code' => 'users.bar_code',
                'created_at' => 'users.created_at',
            ])
            ->defaults(new SortRule('created_at', 'desc'))
            ->apply($builder, $sort);
    }
}
