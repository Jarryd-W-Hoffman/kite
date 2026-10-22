<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\UpdateKeyBindings;
use App\Http\Requests\UpdateKeyBindingsRequest;
use Illuminate\Http\RedirectResponse;

final readonly class KeyBindingController
{
    public function update(UpdateKeyBindingsRequest $request, UpdateKeyBindings $update): RedirectResponse
    {
        $update->handle($request->bindings());

        return back();
    }
}
