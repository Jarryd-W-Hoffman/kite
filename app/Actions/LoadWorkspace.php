<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Note;
use App\Models\NoteLink;
use App\Models\SavedSearch;
use App\Models\TableView;
use App\Models\Vault;
use App\Services\Canvases;
use App\Services\ColourGroups;
use App\Services\DailyNotes;
use App\Services\IndexingProgress;
use App\Services\NoteLocks;
use App\Services\Row;
use App\Services\Templates;
use App\Services\VaultFilesystem;
use App\Services\VaultWatcher;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\OnceProp;
use SensitiveParameter;

final readonly class LoadWorkspace
{
    private const int BACKLINK_LIMIT = 100;

    public function __construct(
        private VaultFilesystem $files,
        private RenderMarkdown $renderMarkdown,
        private IndexingProgress $progress,
        private VaultWatcher $watcher,
        private DailyNotes $daily,
        private ColourGroups $colourGroups,
        private Templates $templates,
        private NoteLocks $locks,
        private Canvases $canvases,
    ) {
        //
    }

    /**
     * @param  string|null  $key  The vault's key while it is unlocked, to open a locked note.
     * @return array<string, mixed>
     */
    public function handle(Vault $vault, ?Note $note = null, #[SensitiveParameter] ?string $key = null): array
    {
        // Read before anything is built, so a change made meanwhile moves to a newer revision.
        $revision = $vault->currentTreeRevision();

        /*
         * The file tree and the lists beside it are big in a large vault and rarely change, so the
         * window keeps them across visits and only downloads them again for a new revision.
         */
        $once = fn (string $prop, Closure $value): OnceProp => Inertia::once($value)->as("{$prop}:{$vault->id}:{$revision}");

        return [
            'vault' => $vault->only(['id', 'name', 'path']),
            'vaults' => Vault::query()
                ->latest('opened_at')
                ->get(['id', 'name', 'path'])
                ->map(fn (Vault $vault): array => $vault->only(['id', 'name', 'path']))
                ->all(),
            'tree_revision' => $revision,
            'tree' => $once('tree', fn (): array => $this->tree($vault)),
            'folders' => $once('folders', fn (): array => $this->files->folders($vault)),
            'colour_groups' => $this->colourGroups->summaries($vault),
            'templates' => $once('templates', fn (): array => $this->templates->all($vault)),
            'daily_dates' => $once('daily_dates', fn (): array => $this->dailyDates($vault)),
            'favourites' => fn (): array => $this->noteLinks($vault, 'starred_at', 'asc', 50),
            'recent_notes' => fn (): array => $this->noteLinks($vault, 'opened_at', 'desc', 6),
            'canvases' => fn (): array => $this->canvases->all($vault),
            'saved_searches' => fn (): array => $vault->savedSearches()->orderBy('name')->get(['id', 'name'])->map(fn (SavedSearch $search): array => ['id' => $search->id, 'name' => $search->name])->all(),
            'table_views' => fn (): array => $vault->tableViews()->orderBy('name')->get(['id', 'name'])->map(fn (TableView $view): array => ['id' => $view->id, 'name' => $view->name])->all(),
            'open_tasks' => fn (): int => $this->templates->exclude(DB::table('note_tasks')->join('notes', 'notes.id', '=', 'note_tasks.note_id')->where('note_tasks.vault_id', $vault->id)->where('note_tasks.done', false), 'notes.path')->count(),
            'tags' => $once('tags', fn (): array => $this->tags($vault)),
            'indexing' => $this->progress->get($vault),
            'watching' => $this->watcher->isWatching($vault),
            'passphrase_set' => fn (): bool => $this->locks->isSetUp($vault),
            'recovery_key_set' => fn (): bool => $this->locks->hasRecoveryKey($vault),
            'note' => $note instanceof Note ? $this->noteDetail($note, $key) : null,
            'backlinks' => $note instanceof Note ? $this->backlinks($note) : [],
            'outgoing' => $note instanceof Note ? $this->outgoing($note) : [],
        ];
    }

    /**
     * Everything needed to edit and preview a single note. A locked note is opened with the
     * vault's key; without it, the note comes back empty and marked as still locked.
     *
     * @return array<string, mixed>
     */
    public function noteDetail(Note $note, #[SensitiveParameter] ?string $key = null): array
    {
        $contents = $this->files->read($note->vault, $note->path);
        $locked = $this->locks->isLocked($contents);

        if ($locked) {
            $contents = $key === null ? null : $this->locks->unlockNote($contents, $key);
        }

        // A note that stays locked renders as nothing.
        $rendered = $this->renderMarkdown->render($note->vault, $contents ?? '', $note->path);

        return [
            ...$note->only(['id', 'path', 'title', 'frontmatter', 'word_count', 'hash', 'modified_at']),
            'contents' => $contents ?? '',
            'tags' => $note->tags()->orderBy('name')->pluck('name')->all(),
            'starred' => $note->starred_at !== null,
            'daily_date' => $this->daily->dateOf($note->path)?->toDateString(),
            'html' => $rendered->html,
            'outline' => $rendered->outline,
            'locked' => $locked,
            'unlocked' => $locked && $contents !== null,
        ];
    }

    /**
     * Every note in the vault, in path order, with the colour group it takes.
     *
     * @return list<array{id: int, path: string, title: string, group: int|null, locked: bool}>
     */
    private function tree(Vault $vault): array
    {
        $groups = $this->colourGroups->assign($vault);

        return array_values(DB::table('notes')
            ->where('vault_id', $vault->id)
            ->orderByRaw('path COLLATE NOCASE')
            ->get(['id', 'path', 'title', 'locked'])
            ->map(fn (object $row): array => [
                'id' => Row::int($row, 'id'),
                'path' => Row::string($row, 'path'),
                'title' => Row::string($row, 'title'),
                'group' => $groups[Row::int($row, 'id')] ?? null,
                'locked' => Row::int($row, 'locked') === 1,
            ])
            ->all());
    }

    /**
     * Every tag in the vault with how many notes use it.
     *
     * @return list<array{name: string, count: int}>
     */
    private function tags(Vault $vault): array
    {
        return array_values(DB::table('note_tags')
            ->where('vault_id', $vault->id)
            ->groupBy('name')
            ->orderBy('name')
            ->get(['name', DB::raw('count(*) as count')])
            ->map(fn (object $row): array => [
                'name' => Row::string($row, 'name'),
                'count' => Row::int($row, 'count'),
            ])
            ->all());
    }

    /**
     * The dates that have a daily note, for the sidebar calendar.
     *
     * @return list<string>
     */
    private function dailyDates(Vault $vault): array
    {
        $dates = [];

        foreach (DB::table('notes')->where('vault_id', $vault->id)->pluck('path') as $path) {
            $date = $this->daily->dateOf(is_string($path) ? $path : '');

            if ($date instanceof CarbonImmutable) {
                $dates[] = $date->toDateString();
            }
        }

        sort($dates);

        return $dates;
    }

    /**
     * Notes with a timestamp set (starred or opened), for the sidebar's shortcuts.
     *
     * @param  'starred_at'|'opened_at'  $column
     * @param  'asc'|'desc'  $direction
     * @return list<array{id: int, title: string, path: string}>
     */
    private function noteLinks(Vault $vault, string $column, string $direction, int $limit): array
    {
        return array_values(DB::table('notes')
            ->where('vault_id', $vault->id)
            ->whereNotNull($column)
            ->orderBy($column, $direction)
            ->limit($limit)
            ->get(['id', 'title', 'path'])
            ->map(fn (object $row): array => [
                'id' => Row::int($row, 'id'),
                'title' => Row::string($row, 'title'),
                'path' => Row::string($row, 'path'),
            ])
            ->all());
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
