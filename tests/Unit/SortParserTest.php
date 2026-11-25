<?php

use App\Services\Sorting\QuerySortBuilder;
use App\Services\Sorting\SortParser;
use App\Services\Sorting\SortRule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

test('sort parser normalizes user input without supplying defaults', function () {
    $parser = new SortParser;

    $missing = $parser->parse(null, null);
    $normalized = $parser->parse(' created_at ', ' DESC ');
    $invalidDirection = $parser->parse('name', 'sideways');

    expect($missing->column)->toBe('')
        ->and($missing->direction)->toBeNull()
        ->and($normalized->column)->toBe('created_at')
        ->and($normalized->direction)->toBe('desc')
        ->and($invalidDirection->column)->toBe('name')
        ->and($invalidDirection->direction)->toBeNull();
});

test('query sort builder owns missing and invalid sort defaults', function () {
    $query = Mockery::mock(Builder::class);
    $model = Mockery::mock(Model::class);
    $query->shouldReceive('getModel')->once()->andReturn($model);
    $query->shouldReceive('orderBy')->once()->with('users.name', 'desc')->andReturnSelf();
    $query->shouldReceive('orderBy')->once()->with('users.id', 'desc')->andReturnSelf();
    $model->shouldReceive('getQualifiedKeyName')->once()->andReturn('users.id');

    try {
        $sort = (new SortParser)->parse('not-allowed', null);
        $result = (new QuerySortBuilder)
            ->allow(['name' => 'users.name'])
            ->defaults(new SortRule('name', 'desc'))
            ->apply($query, $sort);

        expect($result)->toBe($query);
    } finally {
        Mockery::close();
    }
});
