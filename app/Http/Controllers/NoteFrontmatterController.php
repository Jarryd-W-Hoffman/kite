<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\SaveNote;
use App\Actions\UpdateFrontmatter;
use App\Models\Note;
use App\Models\Vault;
use App\Services\Markdown\MarkdownParser;
use App\Services\VaultFilesystem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final readonly class NoteFrontmatterController
{
    /**
     * Set one property straight in the note's file, e.g. from a table cell.
     */
    public function update(Request $request, Vault $vault, Note $note, VaultFilesystem $files, MarkdownParser $parser, UpdateFrontmatter $frontmatter, SaveNote $save): RedirectResponse
    {
        $request->validate([
            'key' => ['required', 'string', 'max:100', 'regex:/^[^:#\n\[\]{},]+$/u'],
            'value' => ['present', 'nullable'],
        ]);

        throw_if($note->locked, ValidationException::withMessages([
            'key' => __('“:note” is locked. Open it to change its properties.', ['note' => $note->title]),
        ]));

        $contents = $files->read($vault, $note->path);
        $properties = $parser->parse($contents, $note->path)->frontmatter;
        $properties[mb_trim($request->string('key')->toString())] = $request->input('value');

        $save->handle($note, $frontmatter->handle($contents, $properties));

        return back();
    }
}
