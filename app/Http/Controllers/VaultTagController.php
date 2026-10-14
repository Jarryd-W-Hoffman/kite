<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\FindNotesWithTag;
use App\Actions\LoadWorkspace;
use App\Models\Vault;
use Inertia\Inertia;
use Inertia\Response;

final readonly class VaultTagController
{
    public function show(Vault $vault, string $tag, LoadWorkspace $workspace, FindNotesWithTag $notes): Response
    {
        $tag = mb_strtolower(mb_trim($tag, " \t#/"));

        return Inertia::render('tag/show', [
            ...$workspace->handle($vault),
            'tag' => $tag,
            'notes' => $notes->handle($vault, $tag),
        ]);
    }
}
