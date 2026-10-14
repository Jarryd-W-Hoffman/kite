<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreateNote;
use App\Models\Vault;
use App\Services\Templates;
use App\Services\VaultFilesystem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

final readonly class TemplateController
{
    private const string STARTER = <<<'MD'
        ---
        created: {{date}}
        tags: []
        ---
        # {{title}}

        Made from a template on {{date:l, j F Y}} at {{time}}, in {{folder}}.

        ## Notes

        MD;

    /**
     * A template's text with its placeholders filled in, for inserting into the note being edited.
     */
    public function show(Request $request, Vault $vault, string $path, Templates $templates, VaultFilesystem $files): JsonResponse
    {
        try {
            $found = $templates->contains($files->normalise($path)) && $files->exists($vault, $path);
        } catch (InvalidArgumentException) {
            $found = false;
        }

        abort_unless($found, 404);

        $folder = $request->string('folder')->toString();

        return response()->json([
            'contents' => $templates->render($files->read($vault, $path), [
                'title' => $request->string('title')->toString(),
                'folder' => $folder,
                'vault' => $vault->name,
            ]),
        ]);
    }

    /**
     * Start a template in the templates folder, showing the placeholders it can use.
     */
    public function store(Vault $vault, CreateNote $action, Templates $templates): RedirectResponse
    {
        $note = $action->handle($vault, 'New template', $templates->folder() ?: 'Templates', self::STARTER);

        return to_route('notes.show', [$vault, $note]);
    }
}
