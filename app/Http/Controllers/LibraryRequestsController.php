<?php

namespace App\Http\Controllers;

use App\DTOs\Queries\LibraryRequestListQueryDTO;
use App\DTOs\Queries\SortingDTO;
use App\Enums\LibraryRequestStatus;
use App\Http\Requests\ApproveLibraryRequestRequest;
use App\Http\Resources\LibraryRequestResource;
use App\Http\Resources\LibraryResource;
use App\Models\Library;
use App\Models\LibraryRequest;
use App\Services\AuthorizationService;
use App\Services\Queries\LibraryRequestQueryService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LibraryRequestsController extends Controller
{
    /**
     * @return AnonymousResourceCollection<int, LibraryRequestResource>
     */
    public function index(
        Request $request,
        AuthorizationService $auth,
        LibraryRequestQueryService $requests,
    ): AnonymousResourceCollection {
        $dto = new LibraryRequestListQueryDTO(
            new SortingDTO($request->query('sort'), $request->query('direction')),
            $request->string('status')->toString() ?: null,
            $request->string('search')->toString() ?: null,
            $request->integer('per_page', 25),
        );

        return LibraryRequestResource::collection(
            $requests->build($dto)->paginate($dto->perPage)->withQueryString()
        )->additional([
            'permissions' => $auth->collectionPermissions($request->user(), LibraryRequest::class),
        ]);
    }

    public function show(LibraryRequest $libraryRequest): LibraryRequestResource
    {
        return new LibraryRequestResource($libraryRequest->load('handledBy'));
    }

    public function approve(ApproveLibraryRequestRequest $request, LibraryRequest $libraryRequest): LibraryResource
    {
        if ($libraryRequest->status !== LibraryRequestStatus::Pending) {
            throw ValidationException::withMessages([
                'status' => __('library_requests.already_processed'),
            ]);
        }

        $library = DB::transaction(function () use ($request, $libraryRequest): Library {
            $library = Library::create([
                'name' => $request->string('name')->toString(),
                'address' => $request->string('address')->toString(),
                'place_id' => $request->integer('place_id'),
                'work_time' => $request->filled('work_time') ? $request->string('work_time')->toString() : null,
                'inv_number_auto' => $request->boolean('inv_number_auto', true),
            ]);

            $libraryRequest->update([
                'status' => LibraryRequestStatus::Approved,
                'handled_by_id' => $request->user()?->id,
                'handled_at' => now(),
            ]);

            return $library;
        });

        return new LibraryResource($library->load(['place.region', 'settings']));
    }

    public function reject(Request $request, LibraryRequest $libraryRequest): LibraryRequestResource
    {
        if ($libraryRequest->status !== LibraryRequestStatus::Pending) {
            throw ValidationException::withMessages([
                'status' => __('library_requests.already_processed'),
            ]);
        }

        $libraryRequest->update([
            'status' => LibraryRequestStatus::Rejected,
            'handled_by_id' => $request->user()?->id,
            'handled_at' => now(),
        ]);

        return new LibraryRequestResource($libraryRequest->load('handledBy'));
    }
}
