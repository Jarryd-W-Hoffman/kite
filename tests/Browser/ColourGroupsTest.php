<?php

declare(strict_types=1);

use App\Actions\IndexVault;
use App\Enums\GroupColour;
use App\Models\ColourGroup;

it('creates colour groups from the graph and hides them from the legend', function (): void {
    $vault = vaultWithFiles([
        'Plan.md' => "# Plan #project\n\n[[Home]]",
        'Roadmap.md' => '# Roadmap #project',
        'Home.md' => '# Home',
    ]);
    resolve(IndexVault::class)->handle($vault);

    $page = visit(route('vault-graph.show', $vault));

    $page->select('@graph-colour', 'group')
        ->assertSeeIn('@colour-group-legend', 'No colour groups yet.')
        ->click('@edit-colour-groups')
        ->assertVisible('@colour-group-editor')
        ->fill('[data-test=new-colour-group] input[aria-label="Group name"]', 'Projects')
        ->fill('[data-test=new-colour-group] input[aria-label="Query"]', 'tag:project')
        ->assertSeeIn('[data-test=new-colour-group] [data-test=colour-group-count]', '2 notes')
        ->click('[data-test=new-colour-group] button[type=submit]')
        ->assertPresent('[data-test=colour-group] input[value="Projects"]')
        ->assertSeeIn('@colour-group-legend', 'Projects')
        ->assertAriaAttribute('@graph-canvas', 'label', 'Graph of 3 notes and 1 links')
        ->click('[data-test=colour-group-legend] li:first-child button')
        ->assertAriaAttribute('@graph-canvas', 'label', 'Graph of 1 notes and 0 links')
        ->assertNoJavaScriptErrors();

    expect(ColourGroup::query()->sole()->only(['name', 'query']))->toBe(['name' => 'Projects', 'query' => 'tag:project']);
});

it('edits, reorders and deletes colour groups in the editor', function (): void {
    $vault = vaultWithFiles(['Plan.md' => '# Plan #project', 'Work/Doc.md' => '# Doc']);
    resolve(IndexVault::class)->handle($vault);

    $first = ColourGroup::factory()->for($vault)->create(['name' => 'Projects', 'query' => 'tag:project', 'colour' => GroupColour::Blue, 'position' => 0]);
    $second = ColourGroup::factory()->for($vault)->create(['name' => 'Work', 'query' => 'folder:Work', 'colour' => GroupColour::Teal, 'position' => 1]);

    visit(route('vault-graph.show', $vault))
        ->assertSeeIn('@colour-group-legend', 'Projects')
        ->click('@edit-colour-groups')
        ->fill('[data-test=colour-group]:first-child input[aria-label="Query"]', 'tag:nothing')
        ->assertSeeIn('[data-test=colour-group]:first-child [data-test=colour-group-count]', '0 notes')
        ->click('[data-test=colour-group]:first-child button[type=submit]')
        ->click('[data-test=colour-group]:first-child button[aria-label="Move down"]')
        ->assertPresent('[data-test=colour-group]:first-child input[value="Work"]')
        ->click('[data-test=colour-group]:first-child button[aria-label="Delete group"]')
        ->assertMissing('input[value="Work"]')
        ->assertNoJavaScriptErrors();

    expect(ColourGroup::query()->pluck('query', 'id')->all())->toBe([$first->id => 'tag:nothing'])
        ->and(ColourGroup::query()->find($second->id))->toBeNull();
});
