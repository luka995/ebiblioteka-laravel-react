<?php

namespace App\Http\Controllers;

use App\Enums\SchoolGrade;
use App\Http\Requests\AssignSchoolClassStudentsRequest;
use App\Http\Requests\StoreSchoolClassRequest;
use App\Http\Requests\UpdateSchoolClassRequest;
use App\Http\Resources\ClassCandidateResource;
use App\Http\Resources\ClassStudentResource;
use App\Http\Resources\SchoolClassResource;
use App\Models\ClassEnrollment;
use App\Models\Library;
use App\Models\SchoolClass;
use App\Models\SchoolYear;
use App\Models\User;
use App\Queries\SchoolClassesSearch;
use App\Services\ActiveLibraryService;
use App\Services\AuthorizationService;
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

    public function options(SchoolYearService $years): JsonResponse
    {
        $activeId = $years->activeId();

        $schoolYears = SchoolYear::query()
            ->orderByDesc('year')
            ->get(['id', 'year'])
            ->map(fn (SchoolYear $year): array => [
                'id' => $year->id,
                'year' => $year->year,
                'name' => $year->name,
                'is_active' => $year->id === $activeId,
            ])
            ->values();

        return response()->json([
            'data' => [
                'school_years' => $schoolYears,
                'active_school_year_id' => $activeId,
                'grades' => array_map(fn (SchoolGrade $grade): array => [
                    'value' => $grade->value,
                    'label' => $grade->getLabel(),
                    'full_label' => $grade->getFullLabel(),
                ], SchoolGrade::cases()),
            ],
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
    ): AnonymousResourceCollection {
        $this->requireClassInActiveLibrary($schoolClass, $activeLibrary, $request->user());

        $students = ClassEnrollment::query()
            ->with(['user', 'assignedBy'])
            ->where('class_id', $schoolClass->id)
            ->where('library_id', $schoolClass->library_id)
            ->where('school_year_id', $schoolClass->school_year_id)
            ->get()
            ->sortBy(fn (ClassEnrollment $enrollment): string => $enrollment->user?->displayName() ?? '')
            ->values();

        return ClassStudentResource::collection($students);
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
