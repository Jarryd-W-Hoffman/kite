<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\RenderMarkdown;
use App\Models\Note;
use App\Models\Vault;
use App\Services\Markdown\MarkdownParser;
use App\Services\Markdown\MarkdownSection;
use App\Services\NoteLocks;
use App\Services\VaultFilesystem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final readonly class NotePeekController
{
    /** Longer notes are cut at a line break after this many characters: a preview, not the note. */
    private const int MAX_LENGTH = 4000;

    /**
     * A note (or a section or block of it) rendered for a hover preview of a link to it. A locked
     * note shows nothing of itself.
     */
    public function show(Request $request, Vault $vault, Note $note, VaultFilesystem $files, MarkdownSection $sections, MarkdownParser $parser, NoteLocks $locks, RenderMarkdown $render): JsonResponse
    {
        $contents = $files->exists($vault, $note->path) ? $files->read($vault, $note->path) : '';
        $section = $request->string('section')->trim()->toString();

        if ($locks->isLocked($contents)) {
            return response()->json(['title' => $note->title, 'path' => $note->path, 'locked' => true, 'html' => '', 'truncated' => false]);
        }

        $part = $section === '' ? null : $sections->extract($contents, $section);
        $markdown = $part ?? $parser->parse($contents, $note->path)->body;
        $truncated = mb_strlen($markdown) > self::MAX_LENGTH;

        if ($truncated) {
            $cut = mb_strrpos(mb_substr($markdown, 0, self::MAX_LENGTH), "\n");
            $markdown = mb_substr($markdown, 0, $cut === false ? self::MAX_LENGTH : $cut);
        }

        // A heading section is titled by its heading as written; a block by its id.
        $partTitle = $part !== null && preg_match('/\A#{1,6}[ \t]+(.+?)[ \t#]*$/m', $part, $heading) === 1 ? $heading[1] : $section;

        return response()->json([
            'title' => $part === null ? $note->title : "{$note->title} › {$partTitle}",
            'path' => $note->path,
            'locked' => false,
            'html' => $render->handle($vault, $markdown, $note->path),
            'truncated' => $truncated,
        ]);
    }
}
