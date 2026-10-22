<?php

declare(strict_types=1);

use App\Actions\IndexVault;
use App\Models\Note;
use App\Services\ConflictCopies;

it('recognises the names sync apps give conflicting copies', function (string $path, ?string $original): void {
    expect(resolve(ConflictCopies::class)->originalPath($path))->toBe($original);
})->with([
    'Dropbox' => ["Plans (Jo Smith's conflicted copy 2026-10-21).md", 'Plans.md'],
    'Dropbox, in a folder' => ["Work/Q4 plans (Jo's conflicted copy 2026-10-21).md", 'Work/Q4 plans.md'],
    'Nextcloud' => ['Plans (conflicted copy 2026-10-21 101530).md', 'Plans.md'],
    'Obsidian Sync' => ['Plans (Conflicted copy laptop 202610211015).md', 'Plans.md'],
    'Syncthing' => ['Daily/2026-10-21.sync-conflict-20261021-101530-ABCD123.md', 'Daily/2026-10-21.md'],
    'a numbered copy, which could be a note of its own' => ['Plans (1).md', null],
    'a note that happens to end in a number' => ['Plans 2.md', null],
    'brackets of its own' => ['Plans (draft).md', null],
    'not a note' => ["Plans (Jo's conflicted copy 2026-10-21).txt", null],
]);

it("finds a note's conflicting copies, and a copy's note", function (): void {
    $vault = vaultWithFiles([
        'Plans.md' => 'mine',
        "Plans (Jo's conflicted copy 2026-10-21).md" => 'theirs',
        'Plans.sync-conflict-20261021-101530-ABCD123.md' => 'other',
        'Plans for later.md' => 'unrelated',
        "Other (Jo's conflicted copy 2026-10-21).md" => 'orphan copy',
    ]);
    resolve(IndexVault::class)->handle($vault);

    $copies = resolve(ConflictCopies::class);
    $plans = Note::query()->where('path', 'Plans.md')->sole();
    $copy = Note::query()->where('path', "Plans (Jo's conflicted copy 2026-10-21).md")->sole();

    expect(array_column($copies->of($plans), 'path'))->toBe([
        "Plans (Jo's conflicted copy 2026-10-21).md",
        'Plans.sync-conflict-20261021-101530-ABCD123.md',
    ])
        ->and($copies->originalOf($copy))->toBe(['id' => $plans->id, 'path' => 'Plans.md', 'title' => 'Plans'])
        ->and($copies->originalOf($plans))->toBeNull()
        ->and($copies->originalOf(Note::query()->where('path', 'like', 'Other%')->sole()))->toBeNull();
});
