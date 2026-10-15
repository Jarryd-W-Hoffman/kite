<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\LoadWorkspace;
use App\Actions\SearchNotes;
use App\Http\Requests\StoreSavedSearchRequest;
use App\Http\Requests\UpdateSavedSearchRequest;
use App\Models\SavedSearch;
use App\Models\Vault;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

final readonly class SavedSearchController
{
    /** How many results a saved search lists. */
    private const int LIMIT = 200;

    /**
     * Keep a search, named after its text unless a name is given.
     */
    public function store(StoreSavedSearchRequest $request, Vault $vault): RedirectResponse
    {
        $query = mb_trim($request->string('query')->toString());
        $name = mb_trim($request->string('name')->toString());

        $search = $vault->savedSearches()->create([
            'name' => mb_substr($name !== '' ? $name : ($query !== '' ? $query : __('Untitled search')), 0, 80),
            'query' => $query,
        ]);

        return to_route('saved-searches.show', [$vault, $search]);
    }

    public function show(Vault $vault, SavedSearch $savedSearch, LoadWorkspace $workspace, SearchNotes $search): Response
    {
        return Inertia::render('searches/show', [
            ...$workspace->handle($vault),
            'search' => $savedSearch->toSummary(),
            'results' => fn (): array => $savedSearch->query === '' ? [] : $search->handle($vault, $savedSearch->query, self::LIMIT),
        ]);
    }

    public function update(UpdateSavedSearchRequest $request, Vault $vault, SavedSearch $savedSearch): RedirectResponse
    {
        $savedSearch->update([
            'name' => mb_trim($request->string('name')->toString()),
            'query' => mb_trim($request->string('query')->toString()),
        ]);

        return back();
    }

    public function destroy(Vault $vault, SavedSearch $savedSearch): RedirectResponse
    {
        $savedSearch->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Deleted the search “:name”.', ['name' => $savedSearch->name])]);

        return to_route('vaults.show', $vault);
    }
}
