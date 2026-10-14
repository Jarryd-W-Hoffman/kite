<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\SaveColourGroup;
use App\Enums\GroupColour;
use App\Http\Requests\StoreColourGroupRequest;
use App\Http\Requests\UpdateColourGroupRequest;
use App\Models\ColourGroup;
use App\Models\Vault;
use Illuminate\Http\RedirectResponse;

final readonly class ColourGroupController
{
    public function store(StoreColourGroupRequest $request, Vault $vault, SaveColourGroup $action): RedirectResponse
    {
        $action->handle(
            $vault,
            $request->string('name')->toString(),
            $request->string('query')->toString(),
            $request->enum('colour', GroupColour::class) ?? GroupColour::Blue,
        );

        return back();
    }

    public function update(UpdateColourGroupRequest $request, Vault $vault, ColourGroup $colourGroup, SaveColourGroup $action): RedirectResponse
    {
        $action->handle(
            $vault,
            $request->string('name')->toString(),
            $request->string('query')->toString(),
            $request->enum('colour', GroupColour::class) ?? $colourGroup->colour,
            $colourGroup,
        );

        return back();
    }

    public function destroy(Vault $vault, ColourGroup $colourGroup): RedirectResponse
    {
        $colourGroup->delete();

        return back();
    }
}
