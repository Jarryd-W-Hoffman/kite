<?php

declare(strict_types=1);

use App\Actions\IndexNote;
use App\Models\Note;
use App\Models\NoteLink;
use Illuminate\Support\Facades\DB;

it('stores note metadata, search text, links and tags', function (): void {
    $vault = vaultWithFiles([
        'Daily/2026-10-12.md' => "---\ntitle: Monday\nmood: good\n---\nMet with [[Ana#Agenda|Ana]] about ![[Kite]]. #meeting",
    ]);

    $note = resolve(IndexNote::class)->handle($vault, 'Daily/2026-10-12.md');

    expect($note->only(['path', 'path_key', 'slug', 'title', 'word_count']))->toBe([
        'path' => 'Daily/2026-10-12.md',
        'path_key' => 'daily/2026-10-12',
        'slug' => '2026-10-12',
        'title' => 'Monday',
        'word_count' => 8,
    ])
        ->and($note->frontmatter)->toBe(['title' => 'Monday', 'mood' => 'good'])
        ->and($note->hash)->toBe(sha1((string) file_get_contents($vault->path.'/Daily/2026-10-12.md')))
        ->and($note->tags()->pluck('name')->all())->toBe(['meeting'])
        ->and($note->outgoingLinks()->get()->map->only(['target', 'heading', 'alias', 'is_embed', 'line'])->all())->toBe([
            ['target' => 'Ana', 'heading' => 'Agenda', 'alias' => 'Ana', 'is_embed' => false, 'line' => 5],
            ['target' => 'Kite', 'heading' => null, 'alias' => null, 'is_embed' => true, 'line' => 5],
        ])
        ->and(DB::table('notes_fts')->where('rowid', $note->id)->value('body'))->toBe('Met with [[Ana#Agenda|Ana]] about ![[Kite]]. #meeting');
});

it('replaces previous links, tags and search text when re-indexed', function (): void {
    $vault = vaultWithFiles(['Note.md' => '[[Old]] #old']);

    $indexNote = resolve(IndexNote::class);
    $indexNote->handle($vault, 'Note.md');

    writeVaultFile($vault, 'Note.md', '[[New]] #new');

    $note = $indexNote->handle($vault, './Note.md');

    expect(Note::query()->count())->toBe(1)
        ->and($note->outgoingLinks()->pluck('target')->all())->toBe(['New'])
        ->and($note->tags()->pluck('name')->all())->toBe(['new'])
        ->and(DB::table('notes_fts')->count())->toBe(1);
});

it('resolves dangling links to a newly indexed note', function (): void {
    $vault = vaultWithFiles([
        'Source.md' => 'See [[Target]]',
    ]);

    $indexNote = resolve(IndexNote::class);
    $indexNote->handle($vault, 'Source.md');

    expect(NoteLink::query()->sole()->target_note_id)->toBeNull();

    writeVaultFile($vault, 'Target.md', '# Target');

    $target = $indexNote->handle($vault, 'Target.md');

    expect(NoteLink::query()->sole()->target_note_id)->toBe($target->id);
});

it('can skip resolving dangling links', function (): void {
    $vault = vaultWithFiles([
        'Source.md' => 'See [[Target]]',
        'Target.md' => '# Target',
    ]);

    $indexNote = resolve(IndexNote::class);
    $indexNote->handle($vault, 'Source.md');
    $indexNote->handle($vault, 'Target.md', resolveDanglingLinks: false);

    expect(NoteLink::query()->sole()->target_note_id)->toBeNull();
});
