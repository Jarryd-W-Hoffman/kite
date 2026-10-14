<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\SaveNote;
use App\Enums\VersionSource;
use App\Models\Note;
use App\Models\NoteVersion;
use App\Models\Vault;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

final readonly class NoteVersionRestoreController
{
    /**
     * Write an old version back. The text it replaces stays in history, so this can be undone.
     */
    public function store(Vault $vault, Note $note, NoteVersion $version, SaveNote $action): RedirectResponse
    {
        $action->handle($note, $version->contents, VersionSource::Restore);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Restored the version from :date.', ['date' => $version->updated_at->toDayDateTimeString()]),
        ]);

        return to_route('notes.show', [$vault, $note]);
    }
}
