<?php

namespace App\Http\Controllers;

use App\DTOs\Queries\SchoolClassStudentListQueryDTO;
use App\DTOs\Queries\SortingDTO;
use App\Enums\SchoolGrade;
use App\Http\Requests\AssignSchoolClassStudentsRequest;
use App\Http\Requests\ClassTransferPreviewRequest;
use App\Http\Requests\ClassTransferRequest;
use App\Http\Requests\StoreSchoolClassRequest;
use App\Http\Requests\UpdateSchoolClassRequest;
use App\Http\Resources\ClassCandidateResource;
use App\Http\Resources\ClassStudentResource;
use App\Http\Resources\SchoolClassResource;
use App\Models\Library;
use App\Models\SchoolClass;
use App\Models\SchoolYear;
use App\Models\User;
use App\Queries\SchoolClassesSearch;
use App\Services\ActiveLibraryService;
use App\Services\AuthorizationService;
use App\Services\ClassTransferService;
use App\Services\Queries\SchoolClassQueryService;
use App\Services\SchoolClassService;
use App\Services\SchoolYearService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SchoolClassesController extends Controller
{
    /**
     * @return AnonymousResourceCollection<int, SchoolClassResource>
     */
    public function index(
        Request $request,
        AuthorizationService $auth,
        ActiveLibraryService $activeLibraryService,
        SchoolYearService $yearsService,
        SchoolClassesSearch $search,
    ): AnonymousResourceCollection {
        $library = $activeLibraryService->requireActiveLibrary($request->user());

        $query = $search->build(
            $library->id,
            $yearsService->activeId(),
            $request->query(),
        );

        return SchoolClassResource::collection(
            $query->paginate($request->integer('per_page', 25))->withQueryString()
        )->additional([
            'permissions' => $auth->collectionPermissions($request->user(), SchoolClass::class),
        ]);
    }

    public function options(
        Request $request,
        ActiveLibraryService $activeLibraryService,
        SchoolYearService $years,
        ClassTransferService $transferService,
    ): JsonResponse {
        $activeId = $years->activeId();
        $library = $activeLibraryService->resolve($request->user());

        $schoolYears = SchoolYear::query()
            ->orderByDesc('year')
            ->get(['id', 'year']);

        $yearsByNumber = $schoolYears->keyBy('year');
        $classesCounts = $library === null
            ? collect()
            : SchoolClass::query()
                ->where('library_id', $library->id)
                ->selectRaw('school_year_id, count(*) as aggregate')
                ->groupBy('school_year_id')
                ->pluck('aggregate', 'school_year_id');

        $schoolYearOptions = $schoolYears
            ->map(fn (SchoolYear $year): array => [
                'id' => $year->id,
                'year' => $year->year,
                'name' => $year->name,
                'is_active' => $year->id === $activeId,
                'classes_count' => (int) $classesCounts->get($year->id, 0),
                'next_school_year_id' => $yearsByNumber->get($year->year + 1)?->id,
                'previous_school_year_id' => $yearsByNumber->get($year->year - 1)?->id,
            ])
            ->values();

        return response()->json([
            'data' => [
                'school_years' => $schoolYearOptions,
                'active_school_year_id' => $activeId,
                'default_transfer_source_school_year_id' => $library === null
                    ? $activeId
                    : $transferService->defaultSourceYearId($library),
                'grades' => array_map(fn (SchoolGrade $grade): array => [
                    'value' => $grade->value,
                    'label' => $grade->getLabel(),
                    'full_label' => $grade->getFullLabel(),
                ], SchoolGrade::cases()),
            ],
        ]);
    }

    public function transferPreview(
        ClassTransferPreviewRequest $request,
        ActiveLibraryService $activeLibrary,
        ClassTransferService $transferService,
    ): JsonResponse {
        $library = $activeLibrary->requireActiveLibrary($request->user());
        $source = SchoolYear::query()->findOrFail($request->integer('source_school_year_id'));

        return response()->json([
            'data' => $transferService->preview($library, $source)->toArray(),
        ]);
    }

    public function transferStudents(
        ClassTransferRequest $request,
        ActiveLibraryService $activeLibrary,
        ClassTransferService $transferService,
    ): JsonResponse {
        $library = $activeLibrary->requireActiveLibrary($request->user());
        $source = SchoolYear::query()->findOrFail($request->integer('source_school_year_id'));
        $target = SchoolYear::query()->findOrFail($request->integer('target_school_year_id'));

        $result = $transferService->transfer(
            $library,
            $source,
            $target,
            $request->mappings(),
            $request->boolean('create_missing_target_classes'),
            $request->user(),
        );

        return response()->json([
            'data' => $result->toArray(),
        ]);
    }

    public function store(
        StoreSchoolClassRequest $request,
        ActiveLibraryService $activeLibrary,
        SchoolClassService $classesService,
    ): SchoolClassResource {
        $library = $activeLibrary->requireActiveLibrary($request->user());
        $class = $classesService->create($request->validated(), $library, $request->user());

        return new SchoolClassResource(
            $class->load(['library', 'schoolYear'])->loadCount('enrollments')
        );
    }

    public function show(
        Request $request,
        SchoolClass $schoolClass,
        ActiveLibraryService $activeLibrary,
    ): SchoolClassResource {
        $this->requireClassInActiveLibrary($schoolClass, $activeLibrary, $request->user());

        return new SchoolClassResource(
            $schoolClass->load(['library', 'schoolYear'])->loadCount('enrollments')
        );
    }

    public function update(
        UpdateSchoolClassRequest $request,
        SchoolClass $schoolClass,
        ActiveLibraryService $activeLibrary,
        SchoolClassService $classesService,
    ): SchoolClassResource {
        $this->requireClassInActiveLibrary($schoolClass, $activeLibrary, $request->user());

        $class = $classesService->update($schoolClass, $request->validated(), $request->user());

        return new SchoolClassResource(
            $class->load(['library', 'schoolYear'])->loadCount('enrollments')
        );
    }

    public function destroy(
        Request $request,
        SchoolClass $schoolClass,
        ActiveLibraryService $activeLibrary,
        SchoolClassService $classesService,
    ): JsonResponse {
        $this->requireClassInActiveLibrary($schoolClass, $activeLibrary, $request->user());
        $classesService->delete($schoolClass);

        return response()->json(status: 204);
    }

    /**
     * @return AnonymousResourceCollection<int, ClassStudentResource>
     */
    public function students(
        Request $request,
        SchoolClass $schoolClass,
        ActiveLibraryService $activeLibrary,
        SchoolClassQueryService $queryService,
    ): AnonymousResourceCollection {
        $this->requireClassInActiveLibrary($schoolClass, $activeLibrary, $request->user());

        $dto = new SchoolClassStudentListQueryDTO(
            new SortingDTO($request->query('sort'), $request->query('direction')),
            $request->integer('per_page', 25),
        );

        return ClassStudentResource::collection(
            $queryService->students($dto, $schoolClass)
                ->paginate($dto->perPage)
                ->withQueryString()
        );
    }

    /**
     * @return AnonymousResourceCollection<int, ClassCandidateResource>
     */
    public function availableStudents(
        Request $request,
        SchoolClass $schoolClass,
        ActiveLibraryService $activeLibrary,
        SchoolClassService $classesService,
    ): AnonymousResourceCollection {
        $this->requireClassInActiveLibrary($schoolClass, $activeLibrary, $request->user());

        $search = trim((string) $request->string('search'));

        return ClassCandidateResource::collection(
            $classesService->availableStudentsQuery($schoolClass, $search)
                ->paginate($request->integer('per_page', 25))
                ->withQueryString()
        );
    }

    public function assignStudents(
        AssignSchoolClassStudentsRequest $request,
        SchoolClass $schoolClass,
        ActiveLibraryService $activeLibrary,
        SchoolClassService $classesService
    ): JsonResponse {
        $this->requireClassInActiveLibrary($schoolClass, $activeLibrary, $request->user());

        return response()->json(
            $classesService->assignStudents($schoolClass, $request->userIds(), $request->user())->toArray()
        );
    }

    public function unassignStudents(
        AssignSchoolClassStudentsRequest $request,
        SchoolClass $schoolClass,
        ActiveLibraryService $activeLibrary,
        SchoolClassService $classesService,
    ): JsonResponse {
        $this->requireClassInActiveLibrary($schoolClass, $activeLibrary, $request->user());

        return response()->json([
            'removed' => $classesService->unassignStudents($schoolClass, $request->userIds(), $request->user()),
        ]);
    }

    private function requireClassInActiveLibrary(
        SchoolClass $class,
        ActiveLibraryService $activeLibrary,
        User $user,
    ): Library {
        $library = $activeLibrary->requireActiveLibrary($user);

        abort_unless((int) $class->library_id === (int) $library->id, 404);

        return $library;
    }
}
