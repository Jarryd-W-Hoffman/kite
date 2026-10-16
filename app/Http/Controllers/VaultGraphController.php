<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\BuildGraph;
use App\Actions\LoadWorkspace;
use App\Models\Vault;
use App\Services\ColourGroupQuery;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

final readonly class VaultGraphController
{
    /**
     * The vault's graph, optionally only the notes a filter matches (`folder:Work -tag:archived`).
     * A filter that doesn't parse is reported and the whole graph shown.
     */
    public function show(Request $request, Vault $vault, LoadWorkspace $workspace, BuildGraph $graph): Response
    {
        $request->validate(['filter' => ['nullable', 'string', 'max:500']]);

        $filter = mb_trim($request->string('filter')->toString());
        $error = null;
        $query = null;

        if ($filter !== '') {
            try {
                $query = ColourGroupQuery::parse($filter);
            } catch (InvalidArgumentException $invalidArgumentException) {
                $error = $invalidArgumentException->getMessage();
            }
        }

        return Inertia::render('graph/show', [
            ...$workspace->handle($vault),
            'graph' => $graph->handle($vault, $query),
            'filter' => $filter,
            'filter_error' => $error,
        ]);
    }
}
