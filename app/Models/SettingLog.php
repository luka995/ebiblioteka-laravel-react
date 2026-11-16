<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['key', 'old_value', 'new_value', 'changed_by_id'])]
class SettingLog extends Model
{
    protected $table = 'settings_logs';

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    protected function oldValue(): Attribute
    {
        return Attribute::make(
            get: fn (mixed $value): mixed => $value === null
                ? null
                : json_decode((string) $value, true),
            set: fn (mixed $value): ?string => $value === null
                ? null
                : json_encode($value, JSON_UNESCAPED_UNICODE),
        );
    }

    protected function newValue(): Attribute
    {
        return Attribute::make(
            get: fn (mixed $value): mixed => $value === null
                ? null
                : json_decode((string) $value, true),
            set: fn (mixed $value): ?string => $value === null
                ? null
                : json_encode($value, JSON_UNESCAPED_UNICODE),
        );
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by_id')->withTrashed();
    }
}
