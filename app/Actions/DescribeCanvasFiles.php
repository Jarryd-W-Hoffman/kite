<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Note;
use App\Models\Vault;
use App\Services\AttachmentTypes;
use App\Services\VaultFilesystem;
use InvalidArgumentException;

final readonly class DescribeCanvasFiles
{
    public function __construct(
        private VaultFilesystem $files,
        private AttachmentTypes $types,
        private RenderMarkdown $renderMarkdown,
    ) {
        //
    }

    /**
     * What each file a canvas shows is, by its path: a note (with its rendered text, unless it's
     * locked), an image or other attachment (with its URL), or missing.
     *
     * @param  list<string>  $paths
     * @return array<string, array{kind: 'note', note_id: int, title: string, html: string, locked: bool}|array{kind: 'image'|'file', url: string}|array{kind: 'missing'}>
     */
    public function handle(Vault $vault, array $paths): array
    {
        $paths = array_values(array_unique($paths));
        $notes = Note::query()
            ->where('vault_id', $vault->id)
            ->whereIn('path', $paths)
            ->get(['id', 'vault_id', 'path', 'title', 'locked'])
            ->keyBy('path');

        $described = [];

        foreach ($paths as $path) {
            $note = $notes->get($path);

            $described[$path] = match (true) {
                $note instanceof Note && $this->files->exists($vault, $note->path) => [
                    'kind' => 'note',
                    'note_id' => $note->id,
                    'title' => $note->title,
                    'html' => $note->locked ? '' : $this->renderMarkdown->handle($vault, $this->files->read($vault, $note->path), $note->path),
                    'locked' => $note->locked,
                ],
                $this->isAttachment($vault, $path) => [
                    'kind' => $this->types->isImage($path) ? 'image' : 'file',
                    'url' => route('vault-files.show', ['vault' => $vault->id, 'path' => $path], absolute: false),
                ],
                default => ['kind' => 'missing'],
            };
        }

        return $described;
    }

    private function isAttachment(Vault $vault, string $path): bool
    {
        try {
            return $this->types->isAttachment($path) && $this->files->exists($vault, $this->files->normalise($path));
        } catch (InvalidArgumentException) {
            return false;
        }
    }
}
