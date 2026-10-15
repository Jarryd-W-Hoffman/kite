<?php

declare(strict_types=1);

use App\Actions\IndexVault;
use App\Actions\LoadWorkspace;
use App\Models\Note;
use App\Models\Vault;
use Illuminate\Support\Facades\File;
use Inertia\OnceProp;

it('loads the vault tree, tags and vault list without a note', function (): void {
    $vault = vaultWithFiles([
        'b.md' => '#Shared',
        'A/z.md' => '#shared #solo',
    ]);
    $other = Vault::factory()->create(['opened_at' => now()->subDay()]);

    resolve(IndexVault::class)->handle($vault);

    $workspace = resolve(LoadWorkspace::class)->handle($vault);

    expect($workspace['vault'])->toBe(['id' => $vault->id, 'name' => $vault->name, 'path' => $vault->path])
        ->and(array_column($workspace['vaults'], 'id'))->toBe([$vault->id, $other->id])
        ->and($workspace['tree'])->toBeInstanceOf(OnceProp::class)
        ->and(array_column($workspace['tree'](), 'path'))->toBe(['A/z.md', 'b.md'])
        ->and($workspace['tree_revision'])->toBe($vault->currentTreeRevision())
        ->and($workspace['tags']())->toBe([
            ['name' => 'shared', 'count' => 2],
            ['name' => 'solo', 'count' => 1],
        ])
        ->and($workspace['indexing'])->toBeNull()
        ->and($workspace['note'])->toBeNull()
        ->and($workspace['backlinks'])->toBe([])
        ->and($workspace['outgoing'])->toBe([]);
});

it('loads the note with contents, html, backlinks and outgoing links', function (): void {
    $vault = vaultWithFiles([
        'Topic.md' => "---\ntags: [idea]\n---\n# Topic\nSee [[Other]], [[Other#Again]], [[Ghost]] and [[Topic]].",
        'Other.md' => "Intro\n  Mentions [[Topic]] here  \nAnd [[Topic|again]]",
        'Gone.md' => 'Also [[Topic]]',
    ]);

    resolve(IndexVault::class)->handle($vault);

    File::delete($vault->path.'/Gone.md');

    $topic = Note::query()->where('path', 'Topic.md')->sole();
    $other = Note::query()->where('path', 'Other.md')->sole();
    $gone = Note::query()->where('path', 'Gone.md')->sole();

    $workspace = resolve(LoadWorkspace::class)->handle($vault, $topic);

    expect($workspace['note'])->toMatchArray([
        'id' => $topic->id,
        'path' => 'Topic.md',
        'title' => 'Topic',
        'tags' => ['idea'],
        'contents' => "---\ntags: [idea]\n---\n# Topic\nSee [[Other]], [[Other#Again]], [[Ghost]] and [[Topic]].",
    ])
        ->and($workspace['note']['html'])->toContain('<h1 id="h-topic">Topic</h1>')
        ->and($workspace['note']['outline'])->toBe([['level' => 1, 'text' => 'Topic', 'slug' => 'topic', 'line' => 4]])
        ->and($workspace['backlinks'])->toBe([
            ['id' => $gone->id, 'title' => 'Gone', 'path' => 'Gone.md', 'line' => 1, 'context' => ''],
            ['id' => $other->id, 'title' => 'Other', 'path' => 'Other.md', 'line' => 2, 'context' => 'Mentions [[Topic]] here'],
            ['id' => $other->id, 'title' => 'Other', 'path' => 'Other.md', 'line' => 3, 'context' => 'And [[Topic|again]]'],
        ])
        ->and($workspace['outgoing'])->toBe([
            ['target' => 'Other', 'note_id' => $other->id, 'title' => 'Other', 'path' => 'Other.md'],
            ['target' => 'Ghost', 'note_id' => null, 'title' => null, 'path' => null],
            ['target' => 'Topic', 'note_id' => $topic->id, 'title' => 'Topic', 'path' => 'Topic.md'],
        ]);
});
