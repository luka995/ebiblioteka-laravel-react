<?php

namespace App\Http\Controllers;

use App\Enums\BarcodePrintJobStatus;
use App\Http\Requests\StoreBarcodePrintJobRequest;
use App\Http\Resources\BarcodePrintJobResource;
use App\Jobs\GenerateBarcodePrintPdf;
use App\Models\BarcodePrintJob;
use App\Services\ActiveLibraryService;
use App\Services\AuthorizationService;
use App\Support\Text;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BarcodePrintJobsController extends Controller
{
    /**
     * @return AnonymousResourceCollection<int, BarcodePrintJobResource>
     */
    public function index(Request $request, AuthorizationService $auth, ActiveLibraryService $activeLibrary): AnonymousResourceCollection
    {
        $user = $request->user();
        $library = $activeLibrary->requireActiveLibrary($user);

        $query = BarcodePrintJob::query()
            ->with(['library', 'user'])
            ->where('library_id', $library->id)
            ->orderByDesc('id');

        $permissions = $auth->collectionPermissions($user, BarcodePrintJob::class);

        return BarcodePrintJobResource::collection(
            $query->paginate($request->integer('per_page', 25))->withQueryString()
        )->additional(['permissions' => $permissions]);
    }

    public function store(StoreBarcodePrintJobRequest $request, ActiveLibraryService $activeLibrary): JsonResponse
    {
        $user = $request->user();
        $library = $activeLibrary->requireActiveLibrary($user);

        $job = BarcodePrintJob::create([
            'library_id' => $library->id,
            'user_id' => $user->id,
            'scope' => BarcodePrintJob::SCOPE_LIBRARY,
            'format' => $request->validated('format'),
            'status' => BarcodePrintJobStatus::Pending,
        ]);

        GenerateBarcodePrintPdf::dispatch($job);

        return (new BarcodePrintJobResource($job->load(['library', 'user'])))
            ->response()
            ->setStatusCode(201);
    }

    public function show(BarcodePrintJob $barcodePrintJob): BarcodePrintJobResource
    {
        return new BarcodePrintJobResource($barcodePrintJob->load(['library', 'user']));
    }

    public function download(BarcodePrintJob $barcodePrintJob): StreamedResponse|JsonResponse
    {
        if ($barcodePrintJob->status !== BarcodePrintJobStatus::Completed || $barcodePrintJob->file_path === null) {
            return response()->json(['message' => __('barcode.errors.not_ready')], 409);
        }

        $disk = Storage::disk('barcode');

        if (! $disk->exists($barcodePrintJob->file_path)) {
            return response()->json(['message' => __('barcode.errors.file_missing')], 404);
        }

        return $disk->download($barcodePrintJob->file_path, $this->downloadFileName($barcodePrintJob));
    }

    public function destroy(BarcodePrintJob $barcodePrintJob): JsonResponse
    {
        $disk = Storage::disk('barcode');

        if ($barcodePrintJob->file_path !== null && $disk->exists($barcodePrintJob->file_path)) {
            $disk->delete($barcodePrintJob->file_path);
        }

        $barcodePrintJob->delete();

        return response()->json(status: 204);
    }

    /**
     * Naziv fajla za download: "<naziv biblioteke> - barkodovi knjiga.pdf".
     */
    private function downloadFileName(BarcodePrintJob $job): string
    {
        $name = Text::lat($job->library?->name ?? '');
        $name = trim(preg_replace('/[\/\\\\]+/', ' ', $name) ?? '');
        $name = Str::ascii($name);
        $name = trim(preg_replace('/[^A-Za-z0-9._ -]+/', '', $name) ?? '');

        if ($name === '') {
            $name = 'biblioteka';
        }

        return $name.' - barkodovi knjiga.pdf';
    }
}
