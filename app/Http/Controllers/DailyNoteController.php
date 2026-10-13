<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\OpenDailyNote;
use App\Http\Requests\OpenDailyNoteRequest;
use App\Models\Vault;
use Illuminate\Http\RedirectResponse;

final readonly class DailyNoteController
{
    public function store(OpenDailyNoteRequest $request, Vault $vault, OpenDailyNote $action): RedirectResponse
    {
        return to_route('notes.show', [$vault, $action->handle($vault, $request->date('date', '!Y-m-d'))]);
    }
}
