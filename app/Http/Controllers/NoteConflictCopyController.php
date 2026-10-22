<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\DeleteNote;
use App\Models\Note;
use App\Models\Vault;
use App\Services\ConflictCopies;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

final readonly class NoteConflictCopyController
{
    /**
     * Delete a sync app's conflicting copy of a note once it has been merged into the note.
     */
    public function destroy(Vault $vault, Note $note, Note $copy, ConflictCopies $copies, DeleteNote $action): RedirectResponse
    {
        abort_unless(
            $note->vault_id === $vault->id && $copy->vault_id === $vault->id && $copies->originalPath($copy->path) === $note->path,
            404,
        );

        $action->handle($copy);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Merged the conflicting copy into “:name” and deleted it.', ['name' => $note->title]),
        ]);

        return to_route('notes.show', [$vault, $note]);
    }
}
