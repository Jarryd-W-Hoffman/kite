<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreNoteCommentRequest;
use App\Http\Requests\UpdateNoteCommentRequest;
use App\Models\Note;
use App\Models\Vault;
use App\Services\CommentStore;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use RuntimeException;

final readonly class NoteCommentController
{
    public function store(StoreNoteCommentRequest $request, Vault $vault, Note $note, CommentStore $comments): JsonResponse
    {
        return response()->json($this->guard(fn (): array => $comments->add($vault, $note->path, [
            'body' => mb_trim($request->string('body')->toString()),
            'quote' => $request->string('quote')->toString(),
            'prefix' => $request->string('prefix')->toString(),
            'suffix' => $request->string('suffix')->toString(),
        ])), 201);
    }

    public function update(UpdateNoteCommentRequest $request, Vault $vault, Note $note, string $comment, CommentStore $comments): JsonResponse
    {
        $changes = [];

        if ($request->has('body')) {
            $changes['body'] = mb_trim($request->string('body')->toString());
        }

        if ($request->has('resolved')) {
            $changes['resolved'] = $request->boolean('resolved');
        }

        foreach (['quote', 'prefix', 'suffix'] as $anchor) {
            if ($request->has($anchor)) {
                $changes[$anchor] = $request->string($anchor)->toString();
            }
        }

        $updated = $this->guard(fn (): ?array => $comments->update($vault, $note->path, $comment, $changes));

        abort_if($updated === null, 404);

        return response()->json($updated);
    }

    public function destroy(Vault $vault, Note $note, string $comment, CommentStore $comments): Response
    {
        abort_unless($this->guard(fn (): bool => $comments->delete($vault, $note->path, $comment)), 404);

        return response()->noContent();
    }

    /**
     * A comments file that can't be read is left alone and reported, never overwritten.
     *
     * @template T
     *
     * @param  Closure(): T  $change
     * @return T
     */
    private function guard(Closure $change): mixed
    {
        try {
            return $change();
        } catch (RuntimeException $runtimeException) {
            abort(409, $runtimeException->getMessage());
        }
    }
}
