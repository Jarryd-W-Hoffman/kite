<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\UpdateFrontmatter;
use App\Http\Requests\UpdateNotePropertiesRequest;
use App\Models\Note;
use App\Models\Vault;
use App\Services\Markdown\MarkdownParser;
use Illuminate\Http\JsonResponse;

final readonly class NotePropertiesController
{
    /**
     * Rewrite the frontmatter of the text being edited and hand it back; the editor saves it as
     * usual, so unsaved edits elsewhere in the note are never lost.
     */
    public function update(UpdateNotePropertiesRequest $request, Vault $vault, Note $note, UpdateFrontmatter $action, MarkdownParser $parser): JsonResponse
    {
        $contents = $action->handle($request->string('contents')->toString(), $request->properties());

        return response()->json([
            'contents' => $contents,
            'properties' => $parser->parse($contents, $note->path)->frontmatter,
        ]);
    }
}
