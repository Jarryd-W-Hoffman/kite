<?php

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

it('lowercases any script in queries, on every connection', function (): void {
    config(['database.connections.second' => ['driver' => 'sqlite', 'database' => ':memory:', 'foreign_key_constraints' => true]]);

    expect(DB::selectOne("select kite_lower('ÉTÉ Ölçek') as value")->value)->toBe('été ölçek')
        ->and(DB::connection('second')->selectOne("select kite_lower('ÉTÉ') as value")->value)->toBe('été')
        ->and(DB::connection('second')->selectOne('select kite_lower(null) as value')->value)->toBeNull();
});

it('leaves a query on a database that doesn’t exist to fail as it always does', function (): void {
    config(['database.connections.missing' => ['driver' => 'sqlite', 'database' => storage_path('framework/testing/missing.sqlite'), 'foreign_key_constraints' => true]]);

    expect(fn () => DB::connection('missing')->select('select 1'))->toThrow(QueryException::class);
});

it('keeps the values of a failed query out of its error, on every connection', function (): void {
    config(['database.connections.second' => ['driver' => 'sqlite', 'database' => ':memory:', 'foreign_key_constraints' => true]]);

    foreach ([DB::connection(), DB::connection('second')] as $connection) {
        $exception = rescue(fn () => $connection->select('select * from missing_table where body = ?', ['My private note']), fn (Throwable $exception): Throwable => $exception, report: false);

        expect($exception)->toBeInstanceOf(QueryException::class)
            ->and($exception->getMessage())->not->toContain('My private note')->toContain('where body = ?')
            ->and($exception->getBindings())->toBe(['My private note']);
    }
});
