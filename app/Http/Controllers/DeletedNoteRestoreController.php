<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\RestoreDeletedNote;
use App\Models\DeletedNote;
use App\Models\Vault;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

final readonly class DeletedNoteRestoreController
{
    /**
     * Bring a deleted note back with its history, and open it.
     */
    public function store(Vault $vault, DeletedNote $deletedNote, RestoreDeletedNote $action): RedirectResponse
    {
        $note = $action->handle($deletedNote);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Restored “:name”.', ['name' => $note->title]),
        ]);

        return to_route('notes.show', [$vault, $note]);
    }
}
