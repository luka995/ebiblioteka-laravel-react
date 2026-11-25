<?php

namespace App\Services\Sorting;

final class SortParser
{
    public function parse(
        ?string $sort,
        ?string $direction,
    ): SortRule {
        $sort = trim((string) $sort);
        $direction = strtolower(trim((string) $direction));

        return new SortRule(
            $sort,
            in_array($direction, ['asc', 'desc'], true) ? $direction : null,
        );
    }
}
