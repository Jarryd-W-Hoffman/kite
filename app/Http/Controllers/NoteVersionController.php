<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Note;
use App\Models\NoteVersion;
use App\Models\Vault;
use Illuminate\Http\JsonResponse;

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
     * A version's text, with the version before it so the change it made can be shown.
     */
    public function show(Vault $vault, Note $note, NoteVersion $version): JsonResponse
    {
        $previous = $note->versions()->where('id', '<', $version->id)->latest('id')->value('contents');

        return response()->json([
            ...$version->toSummary(),
            'contents' => $version->contents,
            'previous' => $previous,
        ]);
    }
}
