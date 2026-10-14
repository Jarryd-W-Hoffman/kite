<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreateNote;
use App\Actions\DeleteNote;
use App\Actions\FindUnlinkedMentions;
use App\Actions\LoadWorkspace;
use App\Actions\RenderMarkdown;
use App\Actions\SaveNote;
use App\Http\Requests\StoreNoteRequest;
use App\Http\Requests\UpdateNoteRequest;
use App\Models\Note;
use App\Models\Vault;
use App\Services\DailyNotes;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

final readonly class NoteController
{
    public function store(StoreNoteRequest $request, Vault $vault, CreateNote $action): RedirectResponse
    {
        try {
            $note = $action->handle(
                $vault,
                $request->string('title')->toString(),
                $request->filled('folder') ? $request->string('folder')->toString() : null,
            );
        } catch (InvalidArgumentException) {
            throw ValidationException::withMessages([
                'folder' => __('That folder is not inside this vault.'),
            ]);
        }

        return to_route('notes.show', [$vault, $note]);
    }

    public function show(Vault $vault, Note $note, LoadWorkspace $action, FindUnlinkedMentions $mentions): Response
    {
        return Inertia::render('note/show', [
            ...$action->handle($vault, $note),
            'mentions' => Inertia::defer(fn (): array => $mentions->handle($note)),
        ]);
    }

    public function update(UpdateNoteRequest $request, Vault $vault, Note $note, SaveNote $action, RenderMarkdown $renderMarkdown, DailyNotes $daily): JsonResponse
    {
        $contents = $request->string('contents')->toString();

        $note = $action->handle($note, $contents);
        $rendered = $renderMarkdown->render($vault, $contents, $note->path);

        return response()->json([
            ...$note->only(['id', 'path', 'title', 'frontmatter', 'word_count', 'hash', 'modified_at']),
            'tags' => $note->tags()->orderBy('name')->pluck('name')->all(),
            'daily_date' => $daily->dateOf($note->path)?->toDateString(),
            'html' => $rendered->html,
            'outline' => $rendered->outline,
        ]);
    }

    public function destroy(Vault $vault, Note $note, DeleteNote $action): RedirectResponse
    {
        $action->handle($note);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Note deleted.'),
        ]);

        return to_route('vaults.show', $vault);
    }
}
