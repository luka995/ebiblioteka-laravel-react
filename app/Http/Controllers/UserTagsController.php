<?php

namespace App\Http\Controllers;

use App\Models\Tag;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class UserTagsController extends Controller
{
    /**
     * Sinhronizuje tagove korisnika unutar jedne biblioteke.
     *
     * Tagovi drugih biblioteka ostaju netaknuti.
     */
    public function sync(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'library_id' => ['required', 'integer', Rule::exists('libraries', 'id')],
            'tag_ids' => ['required', 'array'],
            'tag_ids.*' => ['integer', Rule::exists('tags', 'id')],
        ]);

        $libraryId = (int) $data['library_id'];
        $tagIds = array_values(array_unique(array_map('intval', $data['tag_ids'])));

        $actor = $request->user();

        if (! $actor->managesLibrary($libraryId)) {
            return response()->json(['message' => __('validation.custom.library_not_managed')], 422);
        }

        if (! $this->tagsBelongToLibrary($tagIds, $libraryId)) {
            return response()->json(['message' => __('validation.custom.tag_library_mismatch')], 422);
        }

        if (! $user->libraries()->whereKey($libraryId)->exists()) {
            return response()->json(['message' => __('validation.custom.user_not_library_member')], 422);
        }

        DB::transaction(function () use ($user, $libraryId, $tagIds) {
            $existingIds = $user->tags()
                ->where('tags.library_id', $libraryId)
                ->pluck('tags.id');

            $user->tags()->detach($existingIds);
            $user->tags()->attach($tagIds);
        });

        return response()->json(status: 204);
    }

    /**
     * Dodeljuje (append) tagove većem broju korisnika unutar jedne biblioteke.
     */
    public function assign(Request $request): JsonResponse
    {
        return $this->bulk($request, 'attach');
    }

    /**
     * Uklanja tagove većem broju korisnika unutar jedne biblioteke.
     */
    public function remove(Request $request): JsonResponse
    {
        return $this->bulk($request, 'detach');
    }

    private function bulk(Request $request, string $action): JsonResponse
    {
        $data = $request->validate([
            'user_ids' => ['required', 'array'],
            'user_ids.*' => ['integer', Rule::exists('users', 'id')],
            'library_id' => ['required', 'integer', Rule::exists('libraries', 'id')],
            'tag_ids' => ['required', 'array'],
            'tag_ids.*' => ['integer', Rule::exists('tags', 'id')],
        ]);

        $libraryId = (int) $data['library_id'];
        $userIds = array_values(array_unique(array_map('intval', $data['user_ids'])));
        $tagIds = array_values(array_unique(array_map('intval', $data['tag_ids'])));

        if (! $request->user()->managesLibrary($libraryId)) {
            return response()->json(['message' => __('validation.custom.library_not_managed')], 422);
        }

        if (! $this->tagsBelongToLibrary($tagIds, $libraryId)) {
            return response()->json(['message' => __('validation.custom.tag_library_mismatch')], 422);
        }

        $nonMembers = User::whereIn('id', $userIds)
            ->whereDoesntHave('libraries', fn ($q) => $q->whereKey($libraryId))
            ->exists();

        if ($nonMembers) {
            return response()->json(['message' => __('validation.custom.user_not_library_member')], 422);
        }

        DB::transaction(function () use ($userIds, $tagIds, $action) {
            $users = User::whereIn('id', $userIds)->get();

            foreach ($users as $user) {
                $user->tags()->{$action}($tagIds);
            }
        });

        return response()->json(status: 204);
    }

    /**
     * @param  array<int, int>  $tagIds
     */
    private function tagsBelongToLibrary(array $tagIds, int $libraryId): bool
    {
        $tagLibraries = Tag::whereIn('id', $tagIds)->pluck('library_id', 'id');

        foreach ($tagIds as $tagId) {
            if (! isset($tagLibraries[$tagId]) || (int) $tagLibraries[$tagId] !== $libraryId) {
                return false;
            }
        }

        return true;
    }
}
