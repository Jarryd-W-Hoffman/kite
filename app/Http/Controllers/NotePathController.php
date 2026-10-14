<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\RenameNote;
use App\Http\Requests\RenameNoteRequest;
use App\Models\Note;
use App\Models\Vault;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

final readonly class NotePathController
{
    public function update(RenameNoteRequest $request, Vault $vault, Note $note, RenameNote $action): RedirectResponse
    {
        try {
            $action->handle($note, $request->string('path')->toString());
        } catch (InvalidArgumentException $invalidArgumentException) {
            throw ValidationException::withMessages([
                'path' => str_contains($invalidArgumentException->getMessage(), 'already exists')
                    ? __('A note already exists at that path.')
                    : __('That path is not valid.'),
            ]);
        }

        // Notes keep their id when they move, so the page that asked (the note itself, or another
        // page whose file tree it was dragged in) is still the right place to return to.
        return back();
    }
}
