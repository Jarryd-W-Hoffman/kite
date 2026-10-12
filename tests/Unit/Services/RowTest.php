<?php

declare(strict_types=1);

use App\Services\Row;

it('reads typed values from raw rows', function (): void {
    $row = (object) ['id' => 5, 'numeric' => '7', 'junk' => 'x', 'nothing' => null, 'name' => 'Kite', 'count' => 3, 'list' => []];

    expect(Row::int($row, 'id'))->toBe(5)
        ->and(Row::int($row, 'numeric'))->toBe(7)
        ->and(Row::int($row, 'junk'))->toBe(0)
        ->and(Row::int($row, 'missing'))->toBe(0)
        ->and(Row::nullableInt($row, 'nothing'))->toBeNull()
        ->and(Row::nullableInt($row, 'id'))->toBe(5)
        ->and(Row::string($row, 'name'))->toBe('Kite')
        ->and(Row::string($row, 'count'))->toBe('3')
        ->and(Row::string($row, 'list'))->toBe('');
});
