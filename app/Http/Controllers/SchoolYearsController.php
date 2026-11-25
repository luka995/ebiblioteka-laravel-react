<?php

namespace App\Http\Controllers;

use App\DTOs\Queries\SchoolYearListQueryDTO;
use App\DTOs\Queries\SortingDTO;
use App\Http\Requests\StoreSchoolYearRequest;
use App\Http\Requests\UpdateSchoolYearRequest;
use App\Http\Resources\SchoolYearResource;
use App\Models\SchoolYear;
use App\Services\Queries\SchoolYearQueryService;
use App\Services\SchoolYearService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SchoolYearsController extends Controller
{
    /**
     * @return AnonymousResourceCollection<int, SchoolYearResource>
     */
    public function index(
        Request $request,
        SchoolYearService $years,
        SchoolYearQueryService $queryService,
    ): AnonymousResourceCollection {
        $dto = new SchoolYearListQueryDTO(
            new SortingDTO($request->query('sort'), $request->query('direction')),
            $request->integer('per_page', 25),
        );

        $items = $queryService->build($dto)
            ->paginate($dto->perPage)
            ->withQueryString();

        return SchoolYearResource::collection($items)->additional([
            'meta' => [
                'active_school_year_id' => $years->activeId(),
                'automatic_activation' => $years->automaticActivationEnabled(),
            ],
        ]);
    }

    public function store(StoreSchoolYearRequest $request, SchoolYearService $yearsService): SchoolYearResource
    {
        $schoolYear = $yearsService->create($request->validated(), $request->user());

        return new SchoolYearResource(
            $schoolYear->load('createdBy')->loadCount('classes')
        );
    }

    public function update(
        UpdateSchoolYearRequest $request,
        SchoolYear $schoolYear,
        SchoolYearService $yearsService,
    ): SchoolYearResource {
        $schoolYear = $yearsService->update($schoolYear, $request->validated());

        return new SchoolYearResource(
            $schoolYear->load('createdBy')->loadCount('classes')
        );
    }

    public function destroy(SchoolYear $schoolYear, SchoolYearService $yearsService): JsonResponse
    {
        $yearsService->delete($schoolYear);

        return response()->json(status: 204);
    }

    public function activate(
        Request $request,
        SchoolYear $schoolYear,
        SchoolYearService $yearsService,
    ): SchoolYearResource {
        $schoolYear = $yearsService->activate($schoolYear, $request->user());

        return new SchoolYearResource(
            $schoolYear->load('createdBy')->loadCount('classes')
        );
    }

    public function updateAutomaticActivation(Request $request, SchoolYearService $yearsService): JsonResponse
    {
        $data = $request->validate([
            'automatic_activation' => ['required', 'boolean'],
        ]);

        $schoolYear = $yearsService->setAutomaticActivation((bool) $data['automatic_activation'], $request->user());

        return response()->json([
            'automatic_activation' => $yearsService->automaticActivationEnabled(),
            'active_school_year_id' => $schoolYear?->id,
        ]);
    }
}
