<?php

declare(strict_types=1);

use App\Actions\IndexVault;
use App\Models\ColourGroup;
use App\Models\Note;
use App\Services\ColourGroupQuery;
use App\Services\ColourGroups;

it('assigns each note the first group it matches, in order', function (): void {
    $vault = vaultWithFiles([
        'Work/Plan.md' => "---\nstatus: active\n---\n# Plan #project",
        'Work/Notes.md' => '# Notes',
        'Home.md' => '# Home #project',
        'Elsewhere.md' => '# Elsewhere',
    ]);
    resolve(IndexVault::class)->handle($vault);

    $work = ColourGroup::factory()->for($vault)->create(['query' => 'folder:Work', 'position' => 1]);
    $projects = ColourGroup::factory()->for($vault)->create(['query' => 'tag:project', 'position' => 0]);
    ColourGroup::factory()->for($vault)->create(['query' => 'tag:', 'position' => 2]);

    $assigned = resolve(ColourGroups::class)->assign($vault);
    $id = fn (string $path): int => Note::query()->where('path', $path)->sole()->id;

    expect($assigned)->toEqual([
        $id('Work/Plan.md') => $projects->id,
        $id('Work/Notes.md') => $work->id,
        $id('Home.md') => $projects->id,
    ])->and(resolve(ColourGroups::class)->summaries($vault))->toBe([
        $projects->toSummary(),
        $work->toSummary(),
        ColourGroup::query()->where('position', 2)->sole()->toSummary(),
    ])->and(resolve(ColourGroups::class)->count($vault, ColourGroupQuery::parse('status:active')))->toBe(1);
});

it('skips matching for vaults without groups', function (): void {
    $vault = vaultWithFiles(['A.md' => '# A']);
    resolve(IndexVault::class)->handle($vault);

    expect(resolve(ColourGroups::class)->assign($vault))->toBe([]);
});
