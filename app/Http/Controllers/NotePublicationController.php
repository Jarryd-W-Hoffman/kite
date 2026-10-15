<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\PublishNote;
use App\Models\Note;
use App\Models\Vault;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Native\Desktop\Facades\Shell;

final readonly class NotePublicationController
{
    public function store(Vault $vault, Note $note, PublishNote $action): RedirectResponse
    {
        throw_if($note->locked, ValidationException::withMessages([
            'note' => __('Locked notes can’t be published.'),
        ]));

        $folder = $action->handle($note);
        $index = $folder.DIRECTORY_SEPARATOR.'index.html';

        if (config('nativephp-internal.running')) {
            Shell::showInFolder($index);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Published “:note” to :folder', ['note' => $note->title, 'folder' => $folder]),
        ]);

        return back();
    }
}
