<?php

declare(strict_types=1);

use App\Actions\IndexVault;
use App\Enums\GroupColour;
use App\Models\ColourGroup;
use App\Models\Vault;

beforeEach(function (): void {
    $this->vault = vaultWithFiles(['Work/Plan.md' => '# Plan #project', 'Home.md' => '# Home']);
    resolve(IndexVault::class)->handle($this->vault);
});

it('creates colour groups at the end of the list', function (): void {
    $vault = $this->vault;

    $this->fromRoute('vault-graph.show', $vault)
        ->post(route('colour-groups.store', $vault), ['name' => ' Projects ', 'query' => ' tag:project ', 'colour' => 'violet'])
        ->assertRedirectToRoute('vault-graph.show', $vault);

    $this->post(route('colour-groups.store', $vault), ['name' => 'Work', 'query' => 'folder:Work', 'colour' => 'teal']);

    expect($vault->colourGroups()->get()->map->only(['name', 'query', 'colour', 'position'])->all())->toBe([
        ['name' => 'Projects', 'query' => 'tag:project', 'colour' => GroupColour::Violet, 'position' => 0],
        ['name' => 'Work', 'query' => 'folder:Work', 'colour' => GroupColour::Teal, 'position' => 1],
    ]);
});

it('validates colour groups', function (array $data, string $field, string $message): void {
    $this->fromRoute('vault-graph.show', $this->vault)
        ->post(route('colour-groups.store', $this->vault), [...['name' => 'Group', 'query' => 'tag:x', 'colour' => 'blue'], ...$data])
        ->assertSessionHasErrors([$field => $message]);
})->with([
    'missing name' => [['name' => ''], 'name', 'The name field is required.'],
    'empty query' => [['query' => '   '], 'query', 'The query field is required.'],
    'bare field' => [['query' => 'tag:'], 'query', 'Every term in a colour group query needs a value.'],
    'unknown colour' => [['colour' => 'plaid'], 'colour', 'The selected colour is invalid.'],
]);

it('updates and deletes colour groups', function (): void {
    $vault = $this->vault;
    $group = ColourGroup::factory()->for($vault)->create();

    $this->put(route('colour-groups.update', [$vault, $group]), ['name' => 'Renamed', 'query' => 'folder:Work', 'colour' => 'red'])
        ->assertSessionHasNoErrors();

    expect($group->refresh()->only(['name', 'query', 'colour']))->toBe(['name' => 'Renamed', 'query' => 'folder:Work', 'colour' => GroupColour::Red]);

    $this->delete(route('colour-groups.destroy', [$vault, $group]))->assertRedirect();

    expect(ColourGroup::query()->count())->toBe(0);
});

it('only touches groups that belong to the vault', function (): void {
    $other = ColourGroup::factory()->for(Vault::factory())->create();

    $this->delete(route('colour-groups.destroy', [$this->vault, $other]))->assertNotFound();
});

it('reorders colour groups', function (): void {
    $vault = $this->vault;
    [$a, $b, $c] = ColourGroup::factory()->for($vault)->count(3)->sequence(fn ($sequence): array => ['position' => $sequence->index])->create();

    $this->put(route('colour-group-order.update', $vault), ['ids' => [$c->id, $a->id, $b->id]])->assertSessionHasNoErrors();

    expect($vault->colourGroups()->pluck('id')->all())->toBe([$c->id, $a->id, $b->id]);

    $this->fromRoute('vault-graph.show', $vault)
        ->put(route('colour-group-order.update', $vault), ['ids' => [$c->id, $a->id]])
        ->assertSessionHasErrors(['ids' => 'The colour groups changed; reload and try again.']);
});

it('previews how many notes a query matches', function (): void {
    $vault = $this->vault;

    $this->getJson(route('colour-group-previews.show', [$vault, 'query' => 'tag:project']))
        ->assertOk()
        ->assertExactJson(['count' => 1, 'error' => null]);

    $this->getJson(route('colour-group-previews.show', [$vault, 'query' => 'tag:']))
        ->assertExactJson(['count' => null, 'error' => 'Every term in a colour group query needs a value.']);
});

it('shares colour groups with the workspace and tags tree notes with their group', function (): void {
    $vault = $this->vault;
    $group = ColourGroup::factory()->for($vault)->create(['query' => 'folder:Work']);

    $this->get(route('vault-graph.show', $vault))
        ->assertInertia(fn ($page) => $page
            ->where('colour_groups', [$group->toSummary()])
            ->where('tree.0.path', 'Home.md')
            ->where('tree.0.group', null)
            ->where('tree.1.group', $group->id)
            ->where('graph.nodes', fn ($nodes): bool => collect($nodes)->firstWhere('path', 'Work/Plan.md')['group'] === $group->id));
});
