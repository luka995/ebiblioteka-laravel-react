<?php

namespace App\Services\Sorting;

use Illuminate\Database\Eloquent\Builder;

/**
 * Genericki primenjivac sortiranja nad Eloquent builderom.
 *
 * Sort mape ostaju u domenskim query servisima; ovaj builder samo proverava
 * dozvoljene kolone, bira podrazumevani sort i dodaje stabilan tie-breaker.
 */
final class QuerySortBuilder
{
    /** @var array<string, string|callable(Builder, string): void> */
    private array $sorts = [];

    private ?SortRule $default = null;

    /**
     * @param  array<string, string|callable(Builder, string): void>  $sorts
     */
    public function allow(array $sorts): self
    {
        $this->sorts = $sorts;

        return $this;
    }

    public function defaults(SortRule $rule): self
    {
        $this->default = $rule;

        return $this;
    }

    public function apply(Builder $query, SortRule $rule): Builder
    {
        $column = array_key_exists($rule->column, $this->sorts)
            ? $rule->column
            : ($this->default?->column ?? array_key_first($this->sorts));

        if ($column === null) {
            return $query;
        }

        $handler = $this->sorts[$column] ?? null;

        if ($handler === null) {
            return $query;
        }

        $direction = in_array($rule->direction, ['asc', 'desc'], true)
            ? $rule->direction
            : ($this->default?->direction === 'desc' ? 'desc' : 'asc');

        if (is_callable($handler)) {
            $handler($query, $direction);
        } else {
            $query->orderBy($handler, $direction);
        }

        $query->orderBy($query->getModel()->getQualifiedKeyName(), $direction);

        return $query;
    }
}
