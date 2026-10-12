<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\BuildGraph;
use App\Actions\LoadWorkspace;
use App\Models\Vault;
use Inertia\Inertia;
use Inertia\Response;

final readonly class VaultGraphController
{
    public function show(Vault $vault, LoadWorkspace $workspace, BuildGraph $graph): Response
    {
        return Inertia::render('graph/show', [
            ...$workspace->handle($vault),
            'graph' => $graph->handle($vault),
        ]);
    }
}
