<?php

namespace App\Services;

use App\Models\SchoolYear;
use App\Models\User;

/**
 * Zaseban servis za automatsko kreiranje naredne skolske godine.
 *
 * Koristi ga komanda za podsetnik kada naredna godina jos nije upisana.
 */
class SchoolYearAutoCreationService
{
    /**
     * Vraca predstojecu skolsku godinu prema danasnjem datumu ili je kreira.
     */
    public function ensureNextYear(?User $actor = null): SchoolYear
    {
        $today = today();
        $startYear = $today->month >= 9 ? $today->year + 1 : $today->year;

        return SchoolYear::query()->createOrFirst(
            ['year' => $startYear],
            [
                'starts_on' => sprintf('%d-09-01', $startYear),
                'ends_on' => sprintf('%d-08-31', $startYear + 1),
                'created_by_id' => $actor?->id,
            ],
        );
    }
}
