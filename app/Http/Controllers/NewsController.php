<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreNewsRequest;
use App\Http\Requests\UpdateNewsRequest;
use App\Http\Resources\NewsResource;
use App\Models\News;
use App\Services\AuthorizationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class NewsController extends Controller
{
    /**
     * @return AnonymousResourceCollection<int, NewsResource>|NewsResource[]
     */
    public function index(Request $request, AuthorizationService $auth): AnonymousResourceCollection|array
    {
        $query = News::query()
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = trim((string) $request->string('search'));
                $q->where('title', 'ilike', "%{$term}%");
            })
            ->orderByDesc('date')
            ->orderByDesc('id');

        $permissions = $auth->collectionPermissions($request->user(), News::class);

        if ($request->boolean('all')) {
            return NewsResource::collection($query->get())->additional(['permissions' => $permissions]);
        }

        return NewsResource::collection(
            $query->paginate($request->integer('per_page', 25))->withQueryString()
        )->additional(['permissions' => $permissions]);
    }

    public function store(StoreNewsRequest $request): NewsResource
    {
        $data = $request->validated();
        $data['slug'] = $this->uniqueSlug($data['title']);

        return new NewsResource(News::create($data));
    }

    public function show(News $news): NewsResource
    {
        return new NewsResource($news);
    }

    public function update(UpdateNewsRequest $request, News $news): NewsResource
    {
        $data = $request->validated();

        if ($data['title'] !== $news->title) {
            $data['slug'] = $this->uniqueSlug($data['title'], $news->id);
        }

        $news->update($data);

        return new NewsResource($news);
    }

    public function destroy(News $news): JsonResponse
    {
        $news->delete();

        return response()->json(status: 204);
    }

    /**
     * Otpremanje sličice (thumbnail) na javni disk i vraćanje putanje/URL-a.
     *
     * @return JsonResponse
     */
    public function uploadImage(Request $request): JsonResponse
    {
        $data = $request->validate([
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'directory' => ['nullable', 'string', 'in:news,news/body'],
        ]);

        $directory = $data['directory'] ?? 'news';

        $path = $request->file('image')->store($directory, 'public');

        return response()->json([
            'path' => $path,
            'url' => parse_url(Storage::disk('public')->url($path), PHP_URL_PATH),
        ]);
    }

    private function uniqueSlug(string $title, ?int $ignoreId = null): string
    {
        $base = Str::slug($title);
        $base = $base !== '' ? $base : 'novost';

        $slug = $base;
        $suffix = 1;

        while (News::query()
            ->where('slug', $slug)
            ->when($ignoreId !== null, fn ($q) => $q->whereKeyNot($ignoreId))
            ->exists()) {
            $slug = $base.'-'.++$suffix;
        }

        return $slug;
    }
}
