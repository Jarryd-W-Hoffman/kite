<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\MergeNotes;
use App\Http\Requests\StoreNoteMergeRequest;
use App\Models\Note;
use App\Models\Vault;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use InvalidArgumentException;

final readonly class NoteMergeController
{
    public function store(StoreNoteMergeRequest $request, Vault $vault, Note $note, MergeNotes $action): RedirectResponse
    {
        $target = Note::query()->findOrFail($request->integer('into'));

        try {
            $merged = $action->handle($note, $target);
        } catch (InvalidArgumentException) {
            throw ValidationException::withMessages(['into' => __('Locked notes can’t be merged. Remove the lock first.')]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Merged “:source” into this note.', ['source' => $note->title])]);

        return to_route('notes.show', [$vault, $merged]);
    }
}
