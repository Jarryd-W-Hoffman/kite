<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\ExtractToNote;
use App\Http\Requests\StoreNoteExtractionRequest;
use App\Models\Note;
use App\Models\Vault;
use App\Services\NoteLocks;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

final readonly class NoteExtractionController
{
    public function store(StoreNoteExtractionRequest $request, Vault $vault, Note $note, ExtractToNote $action, NoteLocks $locks): JsonResponse
    {
        try {
            $extracted = $action->handle(
                $note,
                $request->string('title')->toString(),
                $request->string('text')->toString(),
                $locks->keyFrom($vault, $request->header(NoteLocks::HEADER)),
            );
        } catch (InvalidArgumentException) {
            throw ValidationException::withMessages(['text' => __('Unlock the vault to extract from a locked note.')]);
        }

        return response()->json([
            'id' => $extracted->id,
            'title' => $extracted->title,
            'link' => basename($extracted->path, '.md'),
            'url' => route('notes.show', [$vault, $extracted]),
        ], 201);
    }
}
