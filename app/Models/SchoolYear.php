<?php

namespace App\Models;

use App\Services\GlobalSettings;
use Database\Factories\SchoolYearFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Globalna skolska godina. Aktivna godina se cuva u settings tabeli
 * (GlobalSettings::ACTIVE_SCHOOL_YEAR_ID), ne na ovom redu.
 */
#[Fillable(['year', 'starts_on', 'ends_on', 'created_by_id'])]
class SchoolYear extends Model
{
    /** @use HasFactory<SchoolYearFactory> */
    use HasFactory;

    private static ?self $activeSchoolYear = null;

    /**
     * Naziv se izvodi iz pocetne godine, npr. 2026/2027.
     *
     * @var array<int, string>
     */
    protected $appends = ['name'];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'starts_on' => 'date',
            'ends_on' => 'date',
        ];
    }

    protected function name(): Attribute
    {
        return Attribute::make(
            get: fn (): string => sprintf('%d/%d', $this->year, $this->year + 1),
        );
    }

    public static function getActiveSchoolYear(): ?self
    {
        if (self::$activeSchoolYear !== null) {
            return self::$activeSchoolYear;
        }

        $activeSchoolYearId = app(GlobalSettings::class)->get(GlobalSettings::ACTIVE_SCHOOL_YEAR_ID);

        return self::$activeSchoolYear = $activeSchoolYearId === null
            ? null
            : self::query()->find((int) $activeSchoolYearId);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id')->withTrashed();
    }

    /**
     * Odeljenja koja pripadaju ovoj godini.
     *
     * @return HasMany<SchoolClass, $this>
     */
    public function classes(): HasMany
    {
        return $this->hasMany(SchoolClass::class);
    }

    /**
     * Trenutna odeljenja ucenika u ovoj godini.
     *
     * @return HasMany<ClassEnrollment, $this>
     */
    public function classEnrollments(): HasMany
    {
        return $this->hasMany(ClassEnrollment::class);
    }

    /**
     * Istorija promena odeljenja u ovoj godini.
     *
     * @return HasMany<ClassroomHistory, $this>
     */
    public function classroomHistory(): HasMany
    {
        return $this->hasMany(ClassroomHistory::class);
    }
}
