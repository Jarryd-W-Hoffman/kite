<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\ReorderColourGroups;
use App\Http\Requests\UpdateColourGroupOrderRequest;
use App\Models\Vault;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

final readonly class ColourGroupOrderController
{
    public function update(UpdateColourGroupOrderRequest $request, Vault $vault, ReorderColourGroups $action): RedirectResponse
    {
        $ids = array_values(array_map(fn (mixed $id): int => is_numeric($id) ? (int) $id : 0, $request->array('ids')));

        try {
            $action->handle($vault, $ids);
        } catch (InvalidArgumentException) {
            throw ValidationException::withMessages(['ids' => __('The colour groups changed; reload and try again.')]);
        }

        return back();
    }
}
