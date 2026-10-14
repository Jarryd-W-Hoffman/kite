<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\ExtractToNote;
use App\Http\Requests\StoreNoteExtractionRequest;
use App\Models\Note;
use App\Models\Vault;
use Illuminate\Http\JsonResponse;

final readonly class NoteExtractionController
{
    public function store(StoreNoteExtractionRequest $request, Vault $vault, Note $note, ExtractToNote $action): JsonResponse
    {
        $extracted = $action->handle($note, $request->string('title')->toString(), $request->string('text')->toString());

        return response()->json([
            'id' => $extracted->id,
            'title' => $extracted->title,
            'link' => basename($extracted->path, '.md'),
            'url' => route('notes.show', [$vault, $extracted]),
        ], 201);
    }
}
