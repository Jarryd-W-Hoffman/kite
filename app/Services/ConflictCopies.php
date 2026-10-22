<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Note;

/**
 * Copies sync apps leave beside a note when it changed in two places at once, recognised by the
 * names they give them. Names other apps might also use, such as "Plans 2" or "Plans (1)",
 * aren't counted: they're as likely to be notes of their own.
 */
final readonly class ConflictCopies
{
    /** Each captures the original note's name. */
    private const array PATTERNS = [
        // Dropbox ("Plans (Jo's conflicted copy 2026-10-21)"), Nextcloud and ownCloud
        // ("Plans (conflicted copy 2026-10-21 123456)") and Obsidian Sync.
        '/^(.+?) \([^()]*conflicted copy[^()]*\)$/iu',
        // Syncthing ("Plans.sync-conflict-20261021-123456-ABCDEFG").
        '/^(.+)\.sync-conflict-\d{8}-\d{6}-[A-Z0-9]{7}$/u',
    ];

    /**
     * The path of the note a conflicting copy was made of, or null when it isn't one.
     */
    public function originalPath(string $path): ?string
    {
        if (preg_match('/^(.*?)([^\/]+)\.md$/iu', $path, $parts) !== 1) {
            return null;
        }

        foreach (self::PATTERNS as $pattern) {
            if (preg_match($pattern, $parts[2], $name) === 1) {
                return $parts[1].$name[1].'.md';
            }
        }

        return null;
    }

    /**
     * Conflicting copies of a note, beside it in its folder.
     *
     * @return list<array{id: int, path: string, title: string}>
     */
    public function of(Note $note): array
    {
        $prefix = (string) preg_replace('/\.md$/i', '', $note->path);

        return array_values(Note::query()
            ->where('vault_id', $note->vault_id)
            ->whereRaw('substr(path, 1, ?) = ?', [mb_strlen($prefix), $prefix])
            ->whereKeyNot($note->id)
            ->orderBy('path')
            ->get(['id', 'path', 'title'])
            ->filter(fn (Note $copy): bool => $this->originalPath($copy->path) === $note->path)
            ->map(fn (Note $copy): array => ['id' => $copy->id, 'path' => $copy->path, 'title' => $copy->title])
            ->all());
    }

    /**
     * The note a conflicting copy was made of, if it is one and that note still exists.
     *
     * @return array{id: int, path: string, title: string}|null
     */
    public function originalOf(Note $note): ?array
    {
        $path = $this->originalPath($note->path);
        $original = $path === null ? null : Note::query()->where('vault_id', $note->vault_id)->where('path', $path)->first(['id', 'path', 'title']);

        return $original instanceof Note ? ['id' => $original->id, 'path' => $original->path, 'title' => $original->title] : null;
    }
}
