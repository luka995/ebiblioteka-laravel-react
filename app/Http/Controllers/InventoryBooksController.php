<?php

namespace App\Http\Controllers;

use App\Enums\InventoryBookStatus;
use App\Http\Middleware\SetLocale;
use App\Http\Resources\InventoryBookResource;
use App\Jobs\GenerateInventoryBookPdf;
use App\Models\InventoryBook;
use App\Models\Library;
use App\Services\ActiveLibraryService;
use App\Services\AuthorizationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InventoryBooksController extends Controller
{
    /**
     * @return AnonymousResourceCollection<int, InventoryBookResource>
     */
    public function index(Request $request, AuthorizationService $auth, ActiveLibraryService $activeLibrary): AnonymousResourceCollection
    {
        $user = $request->user();
        $library = $activeLibrary->requireActiveLibrary($user);

        $query = InventoryBook::query()->with(['library', 'user']);

        $query->where('library_id', $library->id);

        $query->orderByDesc('id');

        $permissions = $auth->collectionPermissions($user, InventoryBook::class);

        return InventoryBookResource::collection(
            $query->paginate($request->integer('per_page', 25))->withQueryString()
        )->additional(['permissions' => $permissions]);
    }

    public function store(Request $request, ActiveLibraryService $activeLibrary): JsonResponse
    {
        $data = $request->validate([
            'library_id' => ['nullable', 'integer', 'exists:libraries,id'],
            'locale' => ['nullable', 'string', Rule::in(SetLocale::LOCALES)],
        ]);

        $user = $request->user();

        $library = $user->isSuperAdmin() && isset($data['library_id'])
            ? Library::find($data['library_id'])
            : $activeLibrary->resolve($user);

        if (! $library instanceof Library) {
            return response()->json(['message' => __('validation.custom.active_library_required')], 422);
        }

        $record = InventoryBook::create([
            'library_id' => $library->id,
            'user_id' => $user->id,
            'status' => InventoryBookStatus::Pending,
            'locale' => $data['locale'] ?? app()->getLocale(),
        ]);

        GenerateInventoryBookPdf::dispatch($record);

        return (new InventoryBookResource($record->load(['library', 'user'])))
            ->response()
            ->setStatusCode(201);
    }

    public function show(InventoryBook $inventoryBook): InventoryBookResource
    {
        return new InventoryBookResource($inventoryBook->load(['library', 'user']));
    }

    public function download(InventoryBook $inventoryBook): StreamedResponse|JsonResponse
    {
        if ($inventoryBook->status !== InventoryBookStatus::Completed || $inventoryBook->file_path === null) {
            return response()->json(['message' => __('inventory.errors.not_ready')], 409);
        }

        $disk = Storage::disk('inventory');

        if (! $disk->exists($inventoryBook->file_path)) {
            return response()->json(['message' => __('inventory.errors.file_missing')], 404);
        }

        $filename = sprintf('inventarna-knjiga-%d-%d.pdf', $inventoryBook->library_id, $inventoryBook->id);

        return $disk->download($inventoryBook->file_path, $filename);
    }
}
