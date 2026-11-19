<?php

namespace App\Models;

use App\Enums\SchoolGrade;
use Database\Factories\SchoolClassFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Odeljenje u okviru jedne biblioteke i jedne skolske godine.
 */
#[Fillable(['library_id', 'school_year_id', 'grade', 'name', 'teacher_name', 'is_active'])]
class SchoolClass extends Model
{
    /** @use HasFactory<SchoolClassFactory> */
    use HasFactory;

    protected $table = 'classes';

    protected function casts(): array
    {
        return [
            'grade' => SchoolGrade::class,
            'is_active' => 'boolean',
        ];
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
     * Trenutni ucenici ovog odeljenja.
     *
     * @return BelongsToMany<User, $this>
     */
    public function students(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'student_class', 'class_id', 'user_id')
            ->withPivot(['library_id', 'school_year_id', 'assigned_by_id'])
            ->withTimestamps();
    }

    /**
     * Current redovi u student_class za ovo odeljenje.
     *
     * @return HasMany<ClassEnrollment, $this>
     */
    public function enrollments(): HasMany
    {
        return $this->hasMany(ClassEnrollment::class, 'class_id');
    }

    /**
     * Istorija u kojoj je ovo odeljenje novo odeljenje.
     *
     * @return HasMany<ClassroomHistory, $this>
     */
    public function history(): HasMany
    {
        return $this->hasMany(ClassroomHistory::class, 'class_id');
    }

    /**
     * Istorija u kojoj je ovo odeljenje staro odeljenje.
     *
     * @return HasMany<ClassroomHistory, $this>
     */
    public function previousHistory(): HasMany
    {
        return $this->hasMany(ClassroomHistory::class, 'previous_class_id');
    }
}
