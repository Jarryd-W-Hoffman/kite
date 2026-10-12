<?php

declare(strict_types=1);

use App\Actions\IndexVault;
use App\Models\Note;
use App\Models\NoteLink;
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

    expect($summary)->toBe(['added' => 2, 'updated' => 0, 'removed' => 0])
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

    expect($indexVault->handle($vault))->toBe(['added' => 0, 'updated' => 0, 'removed' => 0]);

    writeVaultFile($vault, 'C.md', 'C changed');
    File::delete($vault->path.'/B.md');

    expect($indexVault->handle($vault))->toBe(['added' => 0, 'updated' => 1, 'removed' => 1])
        ->and(NoteLink::query()->sole()->target_note_id)->toBeNull()
        ->and(DB::table('notes_fts')->count())->toBe(2);

    writeVaultFile($vault, 'Sub/B.md', 'B is back');

    expect($indexVault->handle($vault))->toBe(['added' => 1, 'updated' => 0, 'removed' => 0])
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

    expect($indexVault->handle($vault))->toBe(['added' => 0, 'updated' => 0, 'removed' => 520])
        ->and(DB::table('notes_fts')->count())->toBe(0);
});
