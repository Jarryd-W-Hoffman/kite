<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\ToggleTask;
use App\Models\Note;
use App\Models\Vault;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

final readonly class NoteTaskController
{
    public function update(Request $request, Vault $vault, Note $note, int $line, ToggleTask $action): RedirectResponse
    {
        $request->validate([
            'text' => ['required', 'string', 'max:1000'],
            'done' => ['required', 'boolean'],
        ]);

        try {
            $action->handle($note, $line, $request->string('text')->toString(), $request->boolean('done'));
        } catch (InvalidArgumentException $invalidArgumentException) {
            throw ValidationException::withMessages(['task' => $invalidArgumentException->getMessage()]);
        }

        return back();
    }
}
