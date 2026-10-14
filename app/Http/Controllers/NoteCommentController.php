<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreNoteCommentRequest;
use App\Http\Requests\UpdateNoteCommentRequest;
use App\Models\Note;
use App\Models\NoteComment;
use App\Models\Vault;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

final readonly class NoteCommentController
{
    public function store(StoreNoteCommentRequest $request, Vault $vault, Note $note): JsonResponse
    {
        $comment = $note->comments()->create([
            'body' => mb_trim($request->string('body')->toString()),
            'quote' => $request->string('quote')->toString(),
            'prefix' => $request->string('prefix')->toString(),
            'suffix' => $request->string('suffix')->toString(),
        ]);

        return response()->json($comment->toSummary(), 201);
    }

    public function update(UpdateNoteCommentRequest $request, Vault $vault, Note $note, NoteComment $comment): JsonResponse
    {
        $changes = [];

        if ($request->has('body')) {
            $changes['body'] = mb_trim($request->string('body')->toString());
        }

        if ($request->has('resolved')) {
            $changes['resolved_at'] = $request->boolean('resolved') ? now() : null;
        }

        foreach (['quote', 'prefix', 'suffix'] as $anchor) {
            if ($request->has($anchor)) {
                $changes[$anchor] = $request->string($anchor)->toString();
            }
        }

        $comment->update($changes);

        return response()->json($comment->toSummary());
    }

    public function destroy(Vault $vault, Note $note, NoteComment $comment): Response
    {
        $comment->delete();

        return response()->noContent();
    }
}
