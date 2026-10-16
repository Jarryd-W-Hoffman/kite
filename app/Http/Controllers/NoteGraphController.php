<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\BuildLocalGraph;
use App\Models\Note;
use App\Models\Vault;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final readonly class NoteGraphController
{
    /**
     * The notes around a note, up to a few links away, for its local graph.
     */
    public function show(Request $request, Vault $vault, Note $note, BuildLocalGraph $graph): JsonResponse
    {
        $request->validate(['depth' => ['required', 'integer', 'between:1,'.BuildLocalGraph::MAX_DEPTH]]);

        return response()->json($graph->handle($note, $request->integer('depth')));
    }
}
