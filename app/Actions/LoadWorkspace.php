<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Note;
use App\Models\NoteLink;
use App\Models\Vault;
use App\Services\DailyNotes;
use App\Services\IndexingProgress;
use App\Services\Row;
use App\Services\VaultFilesystem;
use App\Services\VaultWatcher;
use Illuminate\Support\Facades\DB;

final readonly class LoadWorkspace
{
    private const int BACKLINK_LIMIT = 100;

    public function __construct(
        private VaultFilesystem $files,
        private RenderMarkdown $renderMarkdown,
        private IndexingProgress $progress,
        private VaultWatcher $watcher,
        private DailyNotes $daily,
    ) {
        //
    }

    /**
     * @return array<string, mixed>
     */
    public function handle(Vault $vault, ?Note $note = null): array
    {
        return [
            'vault' => $vault->only(['id', 'name', 'path']),
            'vaults' => Vault::query()
                ->latest('opened_at')
                ->get(['id', 'name', 'path'])
                ->map(fn (Vault $vault): array => $vault->only(['id', 'name', 'path']))
                ->all(),
            'tree' => DB::table('notes')
                ->where('vault_id', $vault->id)
                ->orderByRaw('path COLLATE NOCASE')
                ->get(['id', 'path', 'title'])
                ->map(fn (object $row): array => [
                    'id' => Row::int($row, 'id'),
                    'path' => Row::string($row, 'path'),
                    'title' => Row::string($row, 'title'),
                ])
                ->all(),
            'folders' => fn (): array => $this->files->folders($vault),
            'tags' => DB::table('note_tags')
                ->where('vault_id', $vault->id)
                ->groupBy('name')
                ->orderBy('name')
                ->get(['name', DB::raw('count(*) as count')])
                ->map(fn (object $row): array => [
                    'name' => Row::string($row, 'name'),
                    'count' => Row::int($row, 'count'),
                ])
                ->all(),
            'indexing' => $this->progress->get($vault),
            'watching' => $this->watcher->isWatching($vault),
            'note' => $note instanceof Note ? $this->noteDetail($note) : null,
            'backlinks' => $note instanceof Note ? $this->backlinks($note) : [],
            'outgoing' => $note instanceof Note ? $this->outgoing($note) : [],
        ];
    }

    /**
     * Everything needed to edit and preview a single note.
     *
     * @return array<string, mixed>
     */
    public function noteDetail(Note $note): array
    {
        $contents = $this->files->read($note->vault, $note->path);
        $rendered = $this->renderMarkdown->render($note->vault, $contents, $note->path);

        return [
            ...$note->only(['id', 'path', 'title', 'frontmatter', 'word_count', 'hash', 'modified_at']),
            'contents' => $contents,
            'tags' => $note->tags()->orderBy('name')->pluck('name')->all(),
            'daily_date' => $this->daily->dateOf($note->path)?->toDateString(),
            'html' => $rendered->html,
            'outline' => $rendered->outline,
        ];
    }

    /**
     * @return list<array{id: int, title: string, path: string, line: int, context: string}>
     */
    private function backlinks(Note $note): array
    {
        $links = NoteLink::query()
            ->with('source:id,vault_id,path,title')
            ->where('target_note_id', $note->id)
            ->where('source_note_id', '!=', $note->id)
            ->orderBy('source_note_id')
            ->orderBy('line')
            ->limit(self::BACKLINK_LIMIT)
            ->get();

        $lines = [];
        $backlinks = [];

        foreach ($links as $link) {
            $source = $link->source;

            $lines[$source->id] ??= explode("\n", str_replace("\r\n", "\n", $this->files->exists($note->vault, $source->path)
                ? $this->files->read($note->vault, $source->path)
                : ''));

            $backlinks[] = [
                'id' => $source->id,
                'title' => $source->title,
                'path' => $source->path,
                'line' => $link->line,
                'context' => mb_trim($lines[$source->id][$link->line - 1] ?? ''),
            ];
        }

        usort($backlinks, fn (array $a, array $b): int => [mb_strtolower($a['title']), $a['line']] <=> [mb_strtolower($b['title']), $b['line']]);

        return $backlinks;
    }

    /**
     * @return list<array{target: string, note_id: int|null, title: string|null, path: string|null}>
     */
    private function outgoing(Note $note): array
    {
        return array_values($note->outgoingLinks()
            ->with('targetNote:id,title,path')
            ->orderBy('line')
            ->get()
            ->unique('target_key')
            ->map(fn (NoteLink $link): array => [
                'target' => $link->target,
                'note_id' => $link->target_note_id,
                'title' => $link->targetNote?->title,
                'path' => $link->targetNote?->path,
            ])
            ->all());
    }
}
