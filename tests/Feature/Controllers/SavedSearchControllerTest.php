<?php

declare(strict_types=1);

use App\Actions\IndexVault;
use App\Models\SavedSearch;
use App\Models\Vault;

beforeEach(function (): void {
    $this->vault = vaultWithFiles([
        'Kite.md' => "# Kite\n\n#project Flying in the wind",
        'Garden.md' => "# Garden\n\n#project Wind breaks for the beds",
        'Diary.md' => '# Diary\n\nA windy day',
        'Templates/Wind.md' => '# Wind template',
    ]);
    resolve(IndexVault::class)->handle($this->vault);
});

it('saves a search named after its text, or untitled', function (): void {
    $this->post(route('saved-searches.store', $this->vault), ['query' => '  tag:project wind  ']);
    $this->post(route('saved-searches.store', $this->vault), ['name' => 'Projects', 'query' => 'tag:project']);
    $this->post(route('saved-searches.store', $this->vault))
        ->assertRedirectToRoute('saved-searches.show', [$this->vault, SavedSearch::query()->latest('id')->first()]);

    expect(SavedSearch::query()->orderBy('id')->get(['name', 'query'])->toArray())->toBe([
        ['name' => 'tag:project wind', 'query' => 'tag:project wind'],
        ['name' => 'Projects', 'query' => 'tag:project'],
        ['name' => 'Untitled search', 'query' => ''],
    ]);
});

it('shows a search with its results and lists it in the sidebar', function (): void {
    $search = SavedSearch::factory()->for($this->vault)->create(['name' => 'Windy projects', 'query' => 'tag:project wind']);
    SavedSearch::factory()->for(Vault::factory())->create(['name' => 'Elsewhere']);

    $this->get(route('saved-searches.show', [$this->vault, $search]))
        ->assertInertia(fn ($page) => $page
            ->component('searches/show')
            ->where('search', ['id' => $search->id, 'name' => 'Windy projects', 'query' => 'tag:project wind'])
            ->where('results', fn ($results): bool => collect($results)->pluck('title')->sort()->values()->all() === ['Garden', 'Kite'])
            ->where('results.0.snippet', fn (string $snippet): bool => str_contains($snippet, '<mark>'))
            ->where('saved_searches', [['id' => $search->id, 'name' => 'Windy projects']]));
});

it('shows no results for an empty search', function (): void {
    $search = SavedSearch::factory()->for($this->vault)->create(['query' => '']);

    $this->get(route('saved-searches.show', [$this->vault, $search]))
        ->assertInertia(fn ($page) => $page->where('results', []));
});

it('renames and changes a search', function (): void {
    $search = SavedSearch::factory()->for($this->vault)->create();

    $this->from(route('saved-searches.show', [$this->vault, $search]))
        ->put(route('saved-searches.update', [$this->vault, $search]), ['name' => ' Diary ', 'query' => ' windy '])
        ->assertRedirectToRoute('saved-searches.show', [$this->vault, $search]);

    expect($search->refresh()->only(['name', 'query']))->toBe(['name' => 'Diary', 'query' => 'windy']);

    $this->put(route('saved-searches.update', [$this->vault, $search]), ['name' => '', 'query' => 'x'])->assertSessionHasErrors('name');
    $this->put(route('saved-searches.update', [$this->vault, $search]), ['name' => str_repeat('a', 81)])->assertSessionHasErrors('name');
});

it('deletes a search', function (): void {
    $search = SavedSearch::factory()->for($this->vault)->create(['name' => 'Old']);

    $this->delete(route('saved-searches.destroy', [$this->vault, $search]))
        ->assertRedirectToRoute('vaults.show', $this->vault)
        ->assertInertiaFlash('toast.message', 'Deleted the search “Old”.');

    expect(SavedSearch::query()->count())->toBe(0);
});

it('keeps searches to their own vault', function (): void {
    $other = SavedSearch::factory()->for(Vault::factory())->create();

    $this->get(route('saved-searches.show', [$this->vault, $other]))->assertNotFound();
    $this->put(route('saved-searches.update', [$this->vault, $other]), ['name' => 'x'])->assertNotFound();
    $this->delete(route('saved-searches.destroy', [$this->vault, $other]))->assertNotFound();

    expect(SavedSearch::query()->count())->toBe(1);
});
