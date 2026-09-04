<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Blameable (created_by / updated_by) — Laravel ekvivalent Yii2 BlameableBehavior.
 *
 * Zahteva kolone `created_by` i `updated_by` (nullable FK ka users) na modelu.
 * Null-safe: u kontekstu bez autentikovanog korisnika (konzola/seeder) ne rusava.
 */
trait Blameable
{
    public static function bootBlameable(): void
    {
        static::creating(function (Model $model) {
            $model->created_by ??= Auth::id();
            $model->updated_by ??= Auth::id();
        });

        static::updating(function (Model $model) {
            $model->updated_by = Auth::id() ?? $model->updated_by;
        });
    }
}
