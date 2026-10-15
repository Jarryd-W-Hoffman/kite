<?php

declare(strict_types=1);

use App\Http\Middleware\HandleInertiaRequests;
use App\Services\Settings;
use App\Actions\CreateFolder;
use App\Actions\DeleteFolder;
use App\Actions\IndexVault;
use App\Actions\RenameFolder;
use App\Actions\RenameNote;
use App\Actions\ReorderColourGroups;
use App\Actions\SaveNote;
use App\Models\ColourGroup;
use App\Models\Note;
use App\Models\Vault;

beforeEach(function (): void {
    $this->vault = vaultWithFiles([
        'Projects/Kite.md' => "---\nstatus: active\n---\n# Kite\n\n#project Text",
        'Ideas.md' => '# Ideas',
    ]);
    resolve(IndexVault::class)->handle($this->vault);

    $this->kite = Note::query()->where('path', 'Projects/Kite.md')->sole();
    $this->visit = fn (array $loaded = []) => $this->withHeaders(array_filter([
        'X-Inertia' => 'true',
        'X-Inertia-Version' => (string) resolve(HandleInertiaRequests::class)->version(request()),
        'X-Inertia-Except-Once-Props' => implode(',', $loaded),
    ]))->get(route('notes.show', [$this->vault, $this->kite]));
    $this->revision = fn (): int => $this->vault->currentTreeRevision();
});

it('sends the tree and its neighbours once per revision', function (): void {
    $revision = ($this->revision)();
    $keys = array_map(fn (string $prop): string => "{$prop}:{$this->vault->id}:{$revision}", ['tree', 'folders', 'templates', 'daily_dates', 'tags']);

    $first = ($this->visit)()->assertOk()->json();

    expect($first['props'])->toHaveKeys(['tree', 'folders', 'templates', 'daily_dates', 'tags'])
        ->and($first['props']['tree_revision'])->toBe($revision)
        ->and(array_keys($first['onceProps']))->toEqualCanonicalizing($keys);

    $second = ($this->visit)($keys)->assertOk()->json();

    expect($second['props'])->not->toHaveKeys(['tree', 'folders', 'templates', 'daily_dates', 'tags'])
        ->and($second['props'])->toHaveKeys(['note', 'colour_groups', 'favourites'])
        ->and(array_keys($second['onceProps']))->toEqualCanonicalizing($keys);

    Vault::markTreeChanged($this->vault->id);

    expect(($this->visit)($keys)->json('props'))->toHaveKeys(['tree', 'tags']);
});

it('keeps the revision while only a note’s text changes', function (): void {
    $revision = ($this->revision)();

    resolve(SaveNote::class)->handle($this->kite, "---\nstatus: active\n---\n# Kite\n\n#project Other text");

    expect(($this->revision)())->toBe($revision);

    $this->putJson(route('notes.update', [$this->vault, $this->kite]), ['contents' => "---\nstatus: active\n---\n# Kite\n\n#project More"])
        ->assertJsonPath('tree_revision', $revision);
});

/**
 * Each change that the workspace sidebar shows, made from inside a test.
 *
 * @return array<string, Closure(): mixed>
 */
function treeChanges(): array
{
    return [
        'title' => fn () => resolve(SaveNote::class)->handle($this->kite, "---\nstatus: active\n---\n# Kite 2\n\n#project Text"),
        'tags' => fn () => resolve(SaveNote::class)->handle($this->kite, "---\nstatus: active\n---\n# Kite\n\n#project #extra Text"),
        'properties' => fn () => resolve(SaveNote::class)->handle($this->kite, "---\nstatus: done\n---\n# Kite\n\n#project Text"),
        'new note' => fn () => $this->post(route('notes.store', $this->vault), ['title' => 'Fresh']),
        'renamed note' => fn () => resolve(RenameNote::class)->handle($this->kite, 'Projects/Kite Two.md'),
        'deleted note' => fn () => $this->delete(route('notes.destroy', [$this->vault, $this->kite])),
        'removed on disk' => function (): void {
            unlink($this->vault->path.'/Ideas.md');
            resolve(IndexVault::class)->handle($this->vault);
        },
        'new folder' => fn () => resolve(CreateFolder::class)->handle($this->vault, 'Archive'),
        'renamed folder' => fn () => resolve(RenameFolder::class)->handle($this->vault, 'Projects', 'Work'),
        'deleted folder' => fn () => resolve(DeleteFolder::class)->handle($this->vault, 'Projects'),
        'new colour group' => fn () => ColourGroup::factory()->for($this->vault)->create(),
        'reordered colour groups' => function (): void {
            $groups = ColourGroup::factory()->for($this->vault)->count(2)->create();
            $revision = ($this->revision)();
            resolve(ReorderColourGroups::class)->handle($this->vault, [$groups[1]->id, $groups[0]->id]);
            expect(($this->revision)())->toBeGreaterThan($revision);
        },
        'deleted colour group' => fn () => ColourGroup::factory()->for($this->vault)->create()->delete(),
        'preferences' => fn () => $this->put(route('preferences.update'), [...resolve(Settings::class)->all(), 'templates_folder' => 'Blueprints']),
    ];
}

it('moves to a new revision when the sidebar would change', function (string $change): void {
    $revision = ($this->revision)();

    treeChanges()[$change]->call($this);

    expect(($this->revision)())->toBeGreaterThan($revision, $change);
})->with(array_keys(treeChanges()));
