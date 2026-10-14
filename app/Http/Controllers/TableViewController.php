<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\ListTableRows;
use App\Actions\LoadWorkspace;
use App\Http\Requests\SaveTableViewRequest;
use App\Models\TableView;
use App\Models\Vault;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

final readonly class TableViewController
{
    public function store(Vault $vault): RedirectResponse
    {
        $view = $vault->tableViews()->create([
            'name' => __('Untitled view'),
            'query' => '',
            'columns' => [],
        ]);

        return to_route('table-views.show', ['vault' => $vault, 'tableView' => $view, 'edit' => 1]);
    }

    public function show(Vault $vault, TableView $tableView, LoadWorkspace $workspace, ListTableRows $rows): Response
    {
        return Inertia::render('views/show', [
            ...$workspace->handle($vault),
            'view' => $tableView->toSummary(),
            ...$rows->handle($tableView),
        ]);
    }

    public function update(SaveTableViewRequest $request, Vault $vault, TableView $tableView): RedirectResponse
    {
        $tableView->update([
            'name' => mb_trim($request->string('name')->toString()),
            'query' => mb_trim($request->string('query')->toString()),
            'columns' => array_values(array_filter((array) $request->input('columns', []), is_string(...))),
            'sort_key' => $request->filled('sort_key') ? $request->string('sort_key')->toString() : null,
            'sort_desc' => $request->boolean('sort_desc'),
        ]);

        return back();
    }

    public function destroy(Vault $vault, TableView $tableView): RedirectResponse
    {
        $tableView->delete();

        return to_route('vaults.show', $vault);
    }
}
