<?php

declare(strict_types=1);

use App\Actions\IndexVault;
use App\Enums\VersionSource;
use App\Models\Note;
use App\Models\NoteLink;
use App\Services\CommentStore;
use App\Services\IndexingProgress;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

it('indexes every markdown file in a vault', function (): void {
    $vault = vaultWithFiles([
        'Home.md' => "---\ntags: [start]\n---\n# Welcome\nGo to [[Projects/Kite]] and [[Missing]]. #inbox",
        'Projects/Kite.md' => "# Kite\nBack to [[home|Home page]].",
        'Projects/notes.txt' => 'ignored',
        '.obsidian/workspace.md' => 'ignored',
        'node_modules/pkg/readme.md' => 'ignored',
    ]);

    $summary = resolve(IndexVault::class)->handle($vault);

    expect($summary)->toBe(['added' => 2, 'updated' => 0, 'removed' => 0, 'moved' => 0])
        ->and($vault->refresh()->indexed_at)->not->toBeNull()
        ->and(Note::query()->pluck('path')->all())->toBe(['Home.md', 'Projects/Kite.md']);

    $home = Note::query()->where('path', 'Home.md')->sole();
    $kite = Note::query()->where('path', 'Projects/Kite.md')->sole();

    expect($home->title)->toBe('Welcome')
        ->and($home->slug)->toBe('home')
        ->and($kite->path_key)->toBe('projects/kite')
        ->and($home->tags()->pluck('name')->all())->toBe(['inbox', 'start'])
        ->and($home->outgoingLinks()->orderBy('id')->pluck('target_note_id')->all())->toBe([$kite->id, null])
        ->and($home->backlinks()->sole()->alias)->toBe('Home page')
        ->and(DB::table('notes_fts')->count())->toBe(2);
});

it('only re-indexes changed files and removes deleted ones', function (): void {
    $vault = vaultWithFiles([
        'A.md' => 'Links to [[B]]',
        'B.md' => 'B body',
        'C.md' => 'C body',
    ]);

    $indexVault = resolve(IndexVault::class);
    $indexVault->handle($vault);

    expect($indexVault->handle($vault))->toBe(['added' => 0, 'updated' => 0, 'removed' => 0, 'moved' => 0]);

    writeVaultFile($vault, 'C.md', 'C changed');
    File::delete($vault->path.'/B.md');

    expect($indexVault->handle($vault))->toBe(['added' => 0, 'updated' => 1, 'removed' => 1, 'moved' => 0])
        ->and(NoteLink::query()->sole()->target_note_id)->toBeNull()
        ->and(DB::table('notes_fts')->count())->toBe(2);

    writeVaultFile($vault, 'Sub/B.md', 'B is back');

    expect($indexVault->handle($vault))->toBe(['added' => 1, 'updated' => 0, 'removed' => 0, 'moved' => 0])
        ->and(NoteLink::query()->sole()->target_note_id)->toBe(Note::query()->where('path', 'Sub/B.md')->value('id'));
});

it('removes large numbers of deleted notes in chunks', function (): void {
    $files = [];

    foreach (range(1, 520) as $number) {
        $files["Note {$number}.md"] = "Note {$number}";
    }

    $vault = vaultWithFiles($files);

    $indexVault = resolve(IndexVault::class);
    $indexVault->handle($vault);

    File::cleanDirectory($vault->path);

    expect($indexVault->handle($vault))->toBe(['added' => 0, 'updated' => 0, 'removed' => 520, 'moved' => 0])
        ->and(DB::table('notes_fts')->count())->toBe(0);
});

it('reports progress and commits in batches', function (): void {
    config(['kite.indexing.batch_size' => 2]);

    $files = [];

    foreach (range(1, 120) as $number) {
        $files["Note {$number}.md"] = "[[Note 1]] {$number}";
    }

    $vault = vaultWithFiles($files);
    $reports = [];

    $summary = resolve(IndexVault::class)->handle($vault, function (int $done, int $total) use (&$reports): void {
        $reports[] = [$done, $total];
    });

    expect($summary['added'])->toBe(120)
        ->and($reports)->toBe([[0, 120], [50, 120], [100, 120], [120, 120]])
        ->and(NoteLink::query()->whereNull('target_note_id')->count())->toBe(0);
});

it('skips indexing while another run holds the vault lock', function (): void {
    $vault = vaultWithFiles(['A.md' => 'a']);

    $lock = resolve(IndexingProgress::class)->lock($vault);
    $lock->get();

    expect(resolve(IndexVault::class)->handle($vault))->toBe(['added' => 0, 'updated' => 0, 'removed' => 0, 'moved' => 0])
        ->and(Note::query()->count())->toBe(0);

    $lock->release();

    expect(resolve(IndexVault::class)->handle($vault)['added'])->toBe(1);
});

it('falls back to the default batch size for invalid config', function (): void {
    config(['kite.indexing.batch_size' => 0]);

    $vault = vaultWithFiles(['A.md' => 'a']);

    expect(resolve(IndexVault::class)->handle($vault)['added'])->toBe(1);
});

it('keeps a note renamed outside Kite, with its history, star and comments', function (): void {
    $vault = vaultWithFiles([
        'Inbox/Old name.md' => "# Plans\nThe plan.",
        'Links.md' => 'See [[Old name]] and [[New name]].',
    ]);

    $indexVault = resolve(IndexVault::class);
    $indexVault->handle($vault);

    $note = Note::query()->where('path', 'Inbox/Old name.md')->sole();
    $note->update(['starred_at' => now()]);
    $note->versions()->create(['contents' => 'Earlier', 'hash' => sha1('Earlier'), 'source' => VersionSource::Edit]);

    $comments = resolve(CommentStore::class);
    File::ensureDirectoryExists(dirname($comments->path($vault, 'Inbox/Old name.md')));
    File::put($comments->path($vault, 'Inbox/Old name.md'), '{"format":1,"comments":[]}');

    File::ensureDirectoryExists($vault->path.'/Archive');
    File::move($vault->path.'/Inbox/Old name.md', $vault->path.'/Archive/New name.md');

    expect($indexVault->handle($vault))->toBe(['added' => 0, 'updated' => 0, 'removed' => 0, 'moved' => 1]);

    $moved = $note->fresh();
    $links = Note::query()->where('path', 'Links.md')->sole()->outgoingLinks()->orderBy('id')->pluck('target_note_id')->all();

    expect($moved?->path)->toBe('Archive/New name.md')
        ->and($moved?->path_key)->toBe('archive/new name')
        ->and($moved?->slug)->toBe('new name')
        ->and($moved?->starred_at)->not->toBeNull()
        ->and($moved?->versions()->count())->toBe(1)
        ->and(File::exists($comments->path($vault, 'Archive/New name.md')))->toBeTrue()
        ->and(File::exists($comments->path($vault, 'Inbox/Old name.md')))->toBeFalse()
        ->and($links)->toBe([null, $note->id])
        ->and(Note::query()->count())->toBe(2);
});

it('pairs moved notes with the same text by file name, and leaves other changes alone', function (): void {
    $vault = vaultWithFiles([
        'A/Other.md' => 'Same text',
        'B/Same.md' => 'Same text',
        'Gone.md' => 'Gone for good',
    ]);

    $indexVault = resolve(IndexVault::class);
    $indexVault->handle($vault);

    $other = Note::query()->where('path', 'A/Other.md')->value('id');
    $same = Note::query()->where('path', 'B/Same.md')->value('id');

    // C/Same.md is paired first, and the first note with its text is A/Other.md.
    File::moveDirectory($vault->path.'/A', $vault->path.'/D');
    File::moveDirectory($vault->path.'/B', $vault->path.'/C');
    File::delete($vault->path.'/Gone.md');
    writeVaultFile($vault, 'Fresh.md', 'Gone for gold');

    expect($indexVault->handle($vault))->toBe(['added' => 1, 'updated' => 0, 'removed' => 1, 'moved' => 2])
        ->and(Note::query()->where('path', 'D/Other.md')->value('id'))->toBe($other)
        ->and(Note::query()->where('path', 'C/Same.md')->value('id'))->toBe($same)
        ->and(Note::query()->pluck('path')->sort()->values()->all())->toBe(['C/Same.md', 'D/Other.md', 'Fresh.md']);
});

it('moves the vault to a new tree revision once per batch, and not for plain text edits', function (): void {
    config(['kite.indexing.batch_size' => 2]);

    $vault = vaultWithFiles(['A.md' => 'a', 'B.md' => 'b', 'C.md' => 'c', 'D.md' => 'd', 'E.md' => 'e']);
    $before = $vault->currentTreeRevision();

    resolve(IndexVault::class)->handle($vault);

    expect($vault->currentTreeRevision())->toBe($before + 3);

    writeVaultFile($vault, 'C.md', 'c, edited');
    $before = $vault->currentTreeRevision();

    expect(resolve(IndexVault::class)->handle($vault)['updated'])->toBe(1)
        ->and($vault->currentTreeRevision())->toBe($before);

    writeVaultFile($vault, 'C.md', '# A new title');

    resolve(IndexVault::class)->handle($vault);

    expect($vault->currentTreeRevision())->toBe($before + 1);
});
