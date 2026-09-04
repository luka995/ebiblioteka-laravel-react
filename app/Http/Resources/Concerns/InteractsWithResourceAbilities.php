<?php

namespace App\Http\Resources\Concerns;

use App\Models\User;
use App\Services\AuthorizationService;

trait InteractsWithResourceAbilities
{
    /**
     * `can` mapa za trenutni resurs, izracunata preko Policy-ja.
     *
     * @param  array<int, string>  $actions
     * @return array<string, bool>
     */
    protected function abilities(?User $actor, array $actions): array
    {
        return app(AuthorizationService::class)->resourceCan($actor, $this->resource, $actions);
    }
}
