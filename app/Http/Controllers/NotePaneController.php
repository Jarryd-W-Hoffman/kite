<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\LoadWorkspace;
use App\Models\Note;
use App\Models\Vault;
use App\Services\NoteLocks;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * A single note as JSON, for opening it in a split pane beside the current note.
 */
final readonly class NotePaneController
{
    public function show(Request $request, Vault $vault, Note $note, LoadWorkspace $workspace, NoteLocks $locks): JsonResponse
    {
        return response()->json(['note' => $workspace->noteDetail($note, $locks->keyFrom($vault, $request->header(NoteLocks::HEADER)))]);
    }
}
