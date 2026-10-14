<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\LinkMention;
use App\Http\Requests\LinkMentionRequest;
use App\Models\Note;
use App\Models\Vault;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

final readonly class NoteMentionController
{
    public function store(LinkMentionRequest $request, Vault $vault, Note $note, LinkMention $action): RedirectResponse
    {
        $source = Note::query()->findOrFail($request->integer('source_id'));

        $linked = $action->handle($source, $note, $request->integer('line'));

        Inertia::flash('toast', $linked
            ? ['type' => 'success', 'message' => __('Linked the mention in :note.', ['note' => $source->title])]
            : ['type' => 'error', 'message' => __('That mention has changed. Nothing was linked.')]);

        return to_route('notes.show', [$vault, $note]);
    }
}
