<?php

namespace Database\Seeders;

use App\Enums\LibraryRole;
use App\Enums\SchoolGrade;
use App\Models\ClassEnrollment;
use App\Models\ClassroomHistory;
use App\Models\Library;
use App\Models\SchoolClass;
use App\Models\SchoolYear;
use App\Models\User;
use App\Services\SchoolYearService;
use App\Support\BarCode;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

/**
 * Razvojni podaci za testiranje prebacivanja ucenika.
 *
 * Kreira prethodnu skolsku godinu (ako ne postoji), po dva odeljenja za
 * svaki razred u biblioteci Akademija Filipovic i ucenike upisane u ta
 * odeljenja. Aktivna godina ostaje bez odeljenja kako bi se mogao testirati
 * scenario automatskog kreiranja ciljnih odeljenja pri transferu.
 */
class ClassTransferDemoSeeder extends Seeder
{
    private const LIBRARY_NAME = 'Akademija Filipovic';

    private const SECTIONS_PER_GRADE = 2;

    private const STUDENTS_PER_CLASS = 15;

    public function run(): void
    {
        $library = Library::query()
            ->where('name', self::LIBRARY_NAME)
            ->where('deleted', false)
            ->firstOrFail();

        $activeYear = app(SchoolYearService::class)->active();
        $activeStartYear = $activeYear?->year
            ?? (now()->month >= 9 ? now()->year : now()->year - 1);
        $previousStartYear = $activeStartYear - 1;

        $previousYear = SchoolYear::query()->firstOrCreate(
            ['year' => $previousStartYear],
            [
                'starts_on' => Carbon::create($previousStartYear, 9, 1)->toDateString(),
                'ends_on' => Carbon::create($previousStartYear + 1, 8, 31)->toDateString(),
            ],
        );

        foreach (SchoolGrade::cases() as $grade) {
            for ($section = 1; $section <= self::SECTIONS_PER_GRADE; $section++) {
                $class = $this->classFor($library, $previousYear, $grade, $section);

                for ($index = 1; $index <= self::STUDENTS_PER_CLASS; $index++) {
                    $user = $this->studentFor($library, $previousYear, $grade, $section, $index);

                    ClassEnrollment::query()->firstOrCreate(
                        [
                            'user_id' => $user->id,
                            'library_id' => $library->id,
                            'school_year_id' => $previousYear->id,
                        ],
                        [
                            'class_id' => $class->id,
                            'assigned_by_id' => null,
                        ],
                    );

                    ClassroomHistory::query()->firstOrCreate(
                        [
                            'user_id' => $user->id,
                            'library_id' => $library->id,
                            'school_year_id' => $previousYear->id,
                            'class_id' => $class->id,
                            'previous_class_id' => null,
                        ],
                        [
                            'previous_school_year_id' => null,
                            'changed_by_id' => null,
                            'changed_at' => now(),
                        ],
                    );
                }
            }
        }
    }

    private function classFor(
        Library $library,
        SchoolYear $year,
        SchoolGrade $grade,
        int $section,
    ): SchoolClass {
        return SchoolClass::query()->firstOrCreate(
            [
                'library_id' => $library->id,
                'school_year_id' => $year->id,
                'name' => $grade->value.'-'.$section,
            ],
            [
                'grade' => $grade,
                'teacher_name' => fake()->name(),
                'is_active' => true,
            ],
        );
    }

    private function studentFor(
        Library $library,
        SchoolYear $year,
        SchoolGrade $grade,
        int $section,
        int $index,
    ): User {
        $email = sprintf(
            'ucenik.%d.%d-%d.%02d@akademija-filipovic.test',
            $year->year,
            $grade->value,
            $section,
            $index,
        );

        $user = User::withTrashed()->where('email', $email)->first();

        if ($user === null) {
            $firstName = fake()->firstName();
            $lastName = fake()->lastName();

            $user = User::query()->create([
                'name' => trim($firstName.' '.$lastName),
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => $email,
                'password' => Hash::make('password'),
                'bar_code' => BarCode::generate(),
            ]);
        } elseif ($user->trashed()) {
            $user->restore();
        }

        if ($user->email_verified_at === null) {
            $user->forceFill(['email_verified_at' => now()])->save();
        }

        $membership = $user->librariesWithTrashed()
            ->whereKey($library->id)
            ->first();

        if ($membership === null) {
            $user->libraries()->attach($library->id, [
                'role' => LibraryRole::User->value,
            ]);
        } elseif ($membership->pivot->deleted_at !== null) {
            $user->librariesWithTrashed()->updateExistingPivot($library->id, [
                'role' => LibraryRole::User->value,
                'deleted_at' => null,
            ]);
        }

        return $user;
    }
}
