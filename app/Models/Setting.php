<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['key', 'value'])]
class Setting extends Model
{
    protected function value(): Attribute
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
}
