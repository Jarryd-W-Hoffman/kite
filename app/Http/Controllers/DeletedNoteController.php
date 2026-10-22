<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\ArchiveNote;
use App\Models\DeletedNote;
use App\Models\Vault;
use App\Services\NoteLocks;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final readonly class DeletedNoteController
{
    /**
     * The vault's recently deleted notes, newest first.
     */
    public function index(Vault $vault, ArchiveNote $archive): JsonResponse
    {
        $archive->purge($vault->id);

        return response()->json([
            'deleted_notes' => $vault->deletedNotes()
                ->withCount('versions')
                ->latest('id')
                ->get()
                ->map(fn (DeletedNote $note): array => [
                    'id' => $note->id,
                    'title' => $note->title,
                    'path' => $note->path,
                    'locked' => $note->locked,
                    'versions' => $note->versions_count,
                    'deleted_at' => $note->created_at->toIso8601String(),
                ])
                ->all(),
            'keep_days' => config()->integer('kite.deleted_notes.keep_days'),
        ]);
    }

    /**
     * A deleted note's last text. A locked note's is opened with the vault's key; without it, it
     * comes back empty.
     */
    public function show(Request $request, Vault $vault, DeletedNote $deletedNote, NoteLocks $locks): JsonResponse
    {
        $contents = $deletedNote->latestVersion()->contents ?? '';

        if ($locks->isLocked($contents)) {
            $key = $locks->keyFrom($vault, $request->header(NoteLocks::HEADER));
            $contents = $key === null ? '' : ($locks->unlockNote($contents, $key) ?? '');
        }

        return response()->json([
            'id' => $deletedNote->id,
            'title' => $deletedNote->title,
            'path' => $deletedNote->path,
            'contents' => $contents,
        ]);
    }
}
