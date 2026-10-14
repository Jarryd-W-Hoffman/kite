<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreateFolder;
use App\Actions\DeleteFolder;
use App\Actions\RenameFolder;
use App\Http\Requests\DestroyFolderRequest;
use App\Http\Requests\StoreFolderRequest;
use App\Http\Requests\UpdateFolderRequest;
use App\Models\Vault;
use App\Services\VaultFilesystem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use InvalidArgumentException;

final readonly class FolderController
{
    public function store(StoreFolderRequest $request, Vault $vault, CreateFolder $action): RedirectResponse
    {
        try {
            $action->handle(
                $vault,
                $request->string('name')->toString(),
                $request->filled('parent') ? $request->string('parent')->toString() : null,
            );
        } catch (InvalidArgumentException $invalidArgumentException) {
            throw ValidationException::withMessages(['name' => $this->message($invalidArgumentException)]);
        }

        return back();
    }

    public function update(UpdateFolderRequest $request, Vault $vault, RenameFolder $action): RedirectResponse
    {
        try {
            $action->handle($vault, $request->string('path')->toString(), $request->string('to')->toString());
        } catch (InvalidArgumentException $invalidArgumentException) {
            throw ValidationException::withMessages(['to' => $this->message($invalidArgumentException)]);
        }

        return back();
    }

    public function destroy(DestroyFolderRequest $request, Vault $vault, DeleteFolder $action, VaultFilesystem $files): RedirectResponse
    {
        $path = $request->string('path')->toString();

        try {
            $action->handle($vault, $path);
        } catch (InvalidArgumentException $invalidArgumentException) {
            throw ValidationException::withMessages(['path' => $this->message($invalidArgumentException)]);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $files->usesTrash()
                ? __('Moved “:name” to the trash.', ['name' => basename($path)])
                : __('Deleted “:name”.', ['name' => basename($path)]),
        ]);

        // The page that asked may have been showing a note inside the folder, which is now gone.
        return $request->boolean('leave') ? to_route('vaults.show', $vault) : back();
    }

    private function message(InvalidArgumentException $exception): string
    {
        $message = $exception->getMessage();

        return match (true) {
            str_contains($message, 'already exists') => __('A folder or file with that name already exists.'),
            str_contains($message, 'is ignored') => __('That folder is ignored in Preferences, so it would not be shown.'),
            str_contains($message, 'inside itself') => __('A folder cannot be moved inside itself.'),
            str_contains($message, 'does not exist') => __('That folder no longer exists.'),
            default => __('That folder name is not valid.'),
        };
    }
}
