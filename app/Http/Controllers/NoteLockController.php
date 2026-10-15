<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\LockNote;
use App\Actions\RemoveNoteLock;
use App\Models\Note;
use App\Models\Vault;
use App\Services\NoteLocks;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use InvalidArgumentException;

final readonly class NoteLockController
{
    public function __construct(private NoteLocks $locks)
    {
        //
    }

    /**
     * Lock a note with the vault's key.
     */
    public function update(Request $request, Vault $vault, Note $note, LockNote $action): RedirectResponse
    {
        try {
            $action->handle($note, $this->key($request, $vault));
        } catch (InvalidArgumentException) {
            throw ValidationException::withMessages(['note' => __('That note is already locked.')]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Locked “:note”.', ['note' => $note->title])]);

        return to_route('notes.show', [$vault, $note]);
    }

    /**
     * Take the lock off a note, leaving it as plain text.
     */
    public function destroy(Request $request, Vault $vault, Note $note, RemoveNoteLock $action): RedirectResponse
    {
        try {
            $note = $action->handle($note, $this->key($request, $vault));
        } catch (InvalidArgumentException) {
            throw ValidationException::withMessages(['note' => __('That note could not be unlocked.')]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Removed the lock from “:note”.', ['note' => $note->title])]);

        return to_route('notes.show', [$vault, $note]);
    }

    private function key(Request $request, Vault $vault): string
    {
        $key = $this->locks->keyFrom($vault, $request->header(NoteLocks::HEADER));

        throw_if($key === null, ValidationException::withMessages(['passphrase' => __('Unlock the vault first.')]));

        return $key;
    }
}
