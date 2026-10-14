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
use App\Services\CommentStore;
use App\Services\DailyNotes;
use App\Services\Templates;
use App\Services\VaultFilesystem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;
use RuntimeException;

final readonly class NoteController
{
    public function store(StoreNoteRequest $request, Vault $vault, CreateNote $action, VaultFilesystem $files, Templates $templates): RedirectResponse
    {
        $title = $request->string('title')->toString();
        $folder = $request->filled('folder') ? $request->string('folder')->toString() : null;
        $contents = null;

        if ($request->filled('template')) {
            $template = $request->string('template')->toString();

            try {
                $found = $files->exists($vault, $template);
            } catch (InvalidArgumentException) {
                $found = false;
            }

            throw_unless($found, ValidationException::withMessages([
                'template' => __('That template could not be found.'),
            ]));

            $contents = $templates->render($files->read($vault, $template), [
                'title' => $title,
                'folder' => $folder === null ? '' : mb_trim($folder, '/'),
                'vault' => $vault->name,
            ]);
        }

        try {
            $note = $action->handle($vault, $title, $folder, $contents);
        } catch (InvalidArgumentException) {
            throw ValidationException::withMessages([
                'folder' => __('That folder is not inside this vault.'),
            ]);
        }

        return to_route('notes.show', [$vault, $note]);
    }

    public function show(Vault $vault, Note $note, LoadWorkspace $action, FindUnlinkedMentions $mentions, CommentStore $comments): Response
    {
        return Inertia::render('note/show', [
            ...$action->handle($vault, $note),
            'mentions' => Inertia::defer(fn (): array => $mentions->handle($note)),
            ...$this->comments($vault, $note, $comments),
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

    public function destroy(Vault $vault, Note $note, DeleteNote $action, VaultFilesystem $files): RedirectResponse
    {
        $action->handle($note);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $files->usesTrash()
                ? __('Moved “:name” to the trash.', ['name' => $note->title])
                : __('Deleted “:name”.', ['name' => $note->title]),
        ]);

        return to_route('vaults.show', $vault);
    }

    /**
     * The note's comments, or an explanation when its comments file can't be read.
     *
     * @return array{comments: list<array<string, mixed>>, comments_error?: string}
     */
    private function comments(Vault $vault, Note $note, CommentStore $comments): array
    {
        try {
            return ['comments' => $comments->all($vault, $note->path)];
        } catch (RuntimeException) {
            return [
                'comments' => [],
                'comments_error' => __('This note’s comments file (:file) could not be read, so comments are unavailable until it is fixed.', [
                    'file' => CommentStore::FOLDER.'/'.$note->path.'.json',
                ]),
            ];
        }
    }
}
