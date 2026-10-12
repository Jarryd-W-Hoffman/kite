<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Typed accessors for raw query builder rows, used on hot paths where hydrating models is too slow.
 */
final class Row
{
    public static function int(object $row, string $column): int
    {
        $value = $row->{$column} ?? null;

        return is_int($value) ? $value : (int) (is_numeric($value) ? $value : 0);
    }

    public static function nullableInt(object $row, string $column): ?int
    {
        return ($row->{$column} ?? null) === null ? null : self::int($row, $column);
    }

    public static function string(object $row, string $column): string
    {
        $value = $row->{$column} ?? null;

        return is_scalar($value) ? (string) $value : '';
    }
}
