<?php

namespace App\Models;

use Database\Factories\ClassEnrollmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Trenutno odeljenje ucenika u jednoj biblioteci i skolskoj godini.
 *
 * Tabela se zove `student_class` (PRD naziv). Istorija promena je u
 * classroom_history; ovaj red uvek predstavlja current stanje.
 */
#[Fillable(['user_id', 'library_id', 'school_year_id', 'class_id', 'assigned_by_id'])]
class ClassEnrollment extends Model
{
    /** @use HasFactory<ClassEnrollmentFactory> */
    use HasFactory;

    protected $table = 'student_class';

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
     * @return BelongsTo<SchoolClass, $this>
     */
    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by_id')->withTrashed();
    }
}
