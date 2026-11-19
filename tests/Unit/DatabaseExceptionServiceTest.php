<?php

use App\Services\DatabaseExceptionService;
use Illuminate\Database\QueryException;

function databaseQueryException(string $sqlState): QueryException
{
    $previous = new PDOException('database error');
    $previous->errorInfo = [$sqlState, 7, 'test error'];

    return new QueryException('pgsql', 'test query', [], $previous);
}

test('detects a postgres foreign key violation by sqlstate', function () {
    expect((new DatabaseExceptionService)->isForeignKeyViolation(databaseQueryException('23503')))->toBeTrue();
});

test('detects a postgres unique violation by sqlstate', function () {
    expect((new DatabaseExceptionService)->isUniqueViolation(databaseQueryException('23505')))->toBeTrue();
});

test('detects a postgres check constraint violation by sqlstate', function () {
    expect((new DatabaseExceptionService)->isCheckConstraintViolation(databaseQueryException('23514')))->toBeTrue();
});

test('does not classify another postgres error as a data integrity violation', function () {
    $exception = databaseQueryException('42601');
    $service = new DatabaseExceptionService;

    expect($service->isForeignKeyViolation($exception))->toBeFalse()
        ->and($service->isUniqueViolation($exception))->toBeFalse()
        ->and($service->isCheckConstraintViolation($exception))->toBeFalse();
});
