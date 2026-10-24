<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\RenameNote;
use App\Actions\RewriteLockedLinks;
use App\Http\Requests\RenameNoteRequest;
use App\Models\Note;
use App\Models\Vault;
use App\Services\NoteLocks;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

final readonly class NotePathController
{
    public function update(RenameNoteRequest $request, Vault $vault, Note $note, RenameNote $action, RewriteLockedLinks $rewriteLockedLinks, NoteLocks $locks): RedirectResponse
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

        $key = $locks->keyFrom($vault, $request->header(NoteLocks::HEADER));

        if ($key !== null) {
            $rewriteLockedLinks->handle($vault, $key);
        }

        // Notes keep their id when they move, so the page that asked (the note itself, or another
        // page whose file tree it was dragged in) is still the right place to return to.
        return back();
    }
}
