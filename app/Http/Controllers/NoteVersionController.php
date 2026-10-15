<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Note;
use App\Models\NoteVersion;
use App\Models\Vault;
use App\Services\NoteLocks;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final readonly class NoteVersionController
{
    public function index(Vault $vault, Note $note): JsonResponse
    {
        return response()->json([
            'versions' => $note->versions()
                ->latest('id')
                ->get()
                ->map(fn (NoteVersion $version): array => $version->toSummary())
                ->all(),
        ]);
    }

    /**
     * A version's text, with the version before it so the change it made can be shown. Versions
     * of a locked note are opened with the vault's key; without it they come back empty.
     */
    public function show(Request $request, Vault $vault, Note $note, NoteVersion $version, NoteLocks $locks): JsonResponse
    {
        $previous = $note->versions()->where('id', '<', $version->id)->latest('id')->value('contents');
        $key = $locks->keyFrom($vault, $request->header(NoteLocks::HEADER));

        $open = function (?string $contents) use ($locks, $key): ?string {
            if ($contents === null || ! $locks->isLocked($contents)) {
                return $contents;
            }

            return $key === null ? '' : $locks->unlockNote($contents, $key);
        };

        return response()->json([
            ...$version->toSummary(),
            'contents' => $open($version->contents),
            'previous' => $open(is_string($previous) ? $previous : null),
            'locked' => $locks->isLocked($version->contents),
        ]);
    }
}
