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
            $note = $action->handle($note, $request->string('path')->toString());
        } catch (InvalidArgumentException $invalidArgumentException) {
            throw ValidationException::withMessages([
                'path' => str_contains($invalidArgumentException->getMessage(), 'already exists')
                    ? __('A note already exists at that path.')
                    : __('That path is not valid.'),
            ]);
        }

        return to_route('notes.show', [$vault, $note]);
    }
}
