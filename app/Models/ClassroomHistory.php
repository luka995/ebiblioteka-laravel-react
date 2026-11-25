<?php

namespace App\Models;

use Database\Factories\ClassroomHistoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only istorija promena odeljenja.
 *
 * `class_id` je novo odeljenje (null = ucenik je uklonjen), a
 * `previous_class_id` je staro odeljenje (null = prvo dodeljivanje).
 * `previous_school_year_id` je godina starog odeljenja i moze biti
 * razlicita od `school_year_id` kada je ucenik prebacen u narednu godinu.
 */
#[Fillable(['user_id', 'library_id', 'school_year_id', 'class_id', 'previous_school_year_id', 'previous_class_id', 'changed_by_id', 'changed_at'])]
class ClassroomHistory extends Model
{
    /** @use HasFactory<ClassroomHistoryFactory> */
    use HasFactory;

    protected $table = 'classroom_history';

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'changed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Library, $this>
     */
    public function library(): BelongsTo
    {
        return $this->belongsTo(Library::class);
    }

    /**
     * @return BelongsTo<SchoolYear, $this>
     */
    public function schoolYear(): BelongsTo
    {
        return $this->belongsTo(SchoolYear::class);
    }

    /**
     * Novo odeljenje u dogadjaju.
     *
     * @return BelongsTo<SchoolClass, $this>
     */
    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    /**
     * Skolska godina starog odeljenja.
     *
     * @return BelongsTo<SchoolYear, $this>
     */
    public function previousSchoolYear(): BelongsTo
    {
        return $this->belongsTo(SchoolYear::class, 'previous_school_year_id');
    }

    /**
     * Staro odeljenje u dogadjaju.
     *
     * @return BelongsTo<SchoolClass, $this>
     */
    public function previousClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'previous_class_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by_id')->withTrashed();
    }
}
