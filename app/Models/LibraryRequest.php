<?php

namespace App\Models;

use App\Enums\LibraryRequestStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'school_name',
    'school_type',
    'city',
    'address',
    'contact_name',
    'contact_email',
    'contact_phone',
    'student_count',
    'employee_count',
    'message',
    'status',
    'handled_by_id',
    'handled_at',
])]
class LibraryRequest extends Model
{
    protected function casts(): array
    {
        return [
            'status' => LibraryRequestStatus::class,
            'student_count' => 'integer',
            'employee_count' => 'integer',
            'handled_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function handledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by_id');
    }

    public function schoolTypeLabel(): string
    {
        return (string) (config('public_site.school_types')[$this->school_type] ?? $this->school_type);
    }
}
