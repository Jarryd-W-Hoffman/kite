<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\LoadWorkspace;
use App\Models\Note;
use App\Models\Vault;
use Illuminate\Http\JsonResponse;

/**
 * A single note as JSON, for opening it in a split pane beside the current note.
 */
final readonly class NotePaneController
{
    public function show(Vault $vault, Note $note, LoadWorkspace $workspace): JsonResponse
    {
        return response()->json(['note' => $workspace->noteDetail($note)]);
    }
}
