<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\ColourGroup;
use App\Models\Vault;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final readonly class ReorderColourGroups
{
    /**
     * Put the vault's colour groups in the given order, which decides which group a note takes.
     *
     * @param  list<int>  $ids  every group id in the vault, in the new order
     */
    public function handle(Vault $vault, array $ids): void
    {
        $existing = $vault->colourGroups()->pluck('id')->map(fn (mixed $id): int => is_numeric($id) ? (int) $id : 0)->all();

        throw_if(
            count($ids) !== count($existing) || array_diff($ids, $existing) !== [],
            InvalidArgumentException::class,
            'The new order must list every colour group in the vault exactly once.',
        );

        DB::transaction(function () use ($ids): void {
            foreach ($ids as $position => $id) {
                ColourGroup::query()->whereKey($id)->update(['position' => $position]);
            }
        });

        Vault::markTreeChanged($vault->id);
    }
}
