<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\ListTasks;
use App\Actions\LoadWorkspace;
use App\Models\Vault;
use Inertia\Inertia;
use Inertia\Response;

final readonly class TaskController
{
    public function index(Vault $vault, LoadWorkspace $workspace, ListTasks $tasks): Response
    {
        return Inertia::render('tasks/index', [
            ...$workspace->handle($vault),
            'tasks' => $tasks->handle($vault),
        ]);
    }
}
